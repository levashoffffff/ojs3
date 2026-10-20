<?php

/**
 * @file JatsExportHandler.php
 *
 * @class JatsExportHandler
 *
 * @brief Handle requests for the JATS bulk export page.
 */

namespace APP\plugins\generic\jatsExport;

use APP\core\Application;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\plugins\generic\jatsExport\classes\MetaforaClient;
use APP\plugins\generic\jatsExport\classes\MetaforaJatsBuilder;
use APP\plugins\generic\jatsExport\classes\MetaforaService;
use APP\submission\Submission;
use APP\template\TemplateManager;
use PKP\core\PKPRequest;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;
use Throwable;

class JatsExportHandler extends Handler
{
    /** Required so Handler::setupTemplate() registers the standard backend
     * JS/CSS bundle (jQuery, the `pkp` global, Vue) — without this, the
     * page renders but stays inert/blank since nothing bootstraps it. */
    public $_isBackendPage = true;

    public JatsExportPlugin $plugin;

    public function __construct(JatsExportPlugin $plugin)
    {
        parent::__construct();
        $this->plugin = $plugin;
        $this->addRoleAssignment(
            [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER],
            ['index', 'export', 'metafora', 'metaforaSettings']
        );
    }

    /**
     * Save (or remove) the Metafora API key and check it right away.
     */
    public function metaforaSettings($args, $request)
    {
        if (!$request->isPost() || !$request->checkCSRF()) {
            $request->redirect(null, 'jatsExport');
            exit;
        }
        $contextId = $request->getContext()->getId();
        $messages = [];

        if ($request->getUserVar('removeKey')) {
            $this->plugin->updateSetting($contextId, 'metaforaApiKey', '');
            $messages[] = ['ok' => true, 'text' => __('plugins.generic.jatsExport.metafora.keyRemoved')];
        } else {
            $key = trim((string) $request->getUserVar('apiKey'));
            if ($key !== '') {
                $this->plugin->updateSetting($contextId, 'metaforaApiKey', $key);
                $messages[] = ['ok' => true, 'text' => __('plugins.generic.jatsExport.metafora.keySaved')];
            }
            if ($client = $this->metaforaClient($request)) {
                $test = $client->testConnection();
                // 404 for a made-up file means the key itself was accepted
                $messages[] = $test->status === 404
                    ? ['ok' => true, 'text' => __('plugins.generic.jatsExport.metafora.connectionOk')]
                    : ['ok' => false, 'text' => __('plugins.generic.jatsExport.metafora.connectionFailed') . ' ' . $test->errorText()];
            }
        }

        $this->flash($request, $messages);
        $request->redirect(null, 'jatsExport');
    }

    /**
     * Metafora actions: a row button sends "action:submissionId", the
     * toolbar buttons send an action for every checked article.
     */
    public function metafora($args, $request)
    {
        if (!$request->isPost() || !$request->checkCSRF()) {
            $request->redirect(null, 'jatsExport');
            exit;
        }
        $client = $this->metaforaClient($request);
        if (!$client) {
            $this->flash($request, [['ok' => false, 'text' => __('plugins.generic.jatsExport.metafora.noKey')]]);
            $request->redirect(null, 'jatsExport');
        }

        if ($row = (string) $request->getUserVar('metaforaRow')) {
            [$action, $id] = array_pad(explode(':', $row, 2), 2, 0);
            $ids = [(int) $id];
        } else {
            $action = (string) $request->getUserVar('metaforaAction');
            $ids = array_filter(array_map('intval', (array) $request->getUserVar('submissionIds')));
        }

        $context = $request->getContext();
        $submissions = $ids ? Repo::submission()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByStatus([Submission::STATUS_PUBLISHED])
            ->filterBySubmissionIds($ids)
            ->getMany()
            ->all() : [];
        if (!$submissions) {
            $this->flash($request, [['ok' => false, 'text' => __('plugins.generic.jatsExport.metafora.nothingSelected')]]);
            $request->redirect(null, 'jatsExport');
        }

        @set_time_limit(0);
        $service = new MetaforaService($client, $context);
        $messages = [];
        if ($action === 'refresh') {
            foreach ($service->refresh($submissions) as $id => $result) {
                if (!$result['ok']) {
                    $messages[] = ['ok' => false, 'text' => '#' . $id . ': ' . $result['message']];
                }
            }
            $messages[] = ['ok' => true, 'text' => __('plugins.generic.jatsExport.metafora.refreshed')];
        } elseif (in_array($action, ['send', 'resend', 'sign', 'unsign', 'delete'], true)) {
            foreach ($submissions as $submission) {
                $result = $service->{$action}($submission);
                $messages[] = ['ok' => $result['ok'], 'text' => '#' . $submission->getId() . ': ' . $result['message']];
            }
        }

        $this->flash($request, $messages);
        $request->redirect(null, 'jatsExport');
    }

    protected function metaforaClient(PKPRequest $request): ?MetaforaClient
    {
        $key = (string) $this->plugin->getSetting($request->getContext()->getId(), 'metaforaApiKey');
        return $key !== '' ? new MetaforaClient($key) : null;
    }

    /** Result lines shown once at the top of the page after the redirect */
    protected function flash(PKPRequest $request, array $messages): void
    {
        $request->getSession()->put('jatsExportMessages', $messages);
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Show the list of published articles with checkboxes.
     */
    public function index($args, $request)
    {
        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);

        // A plain <style>/<script> tag inside the page body gets discarded by
        // the Vue admin shell, so both go through the proper registration API
        // (same mechanism every other backend page's assets use).
        $templateMgr->addStyleSheet(
            'jatsExport',
            $this->getInlineStyles(),
            ['inline' => true, 'contexts' => ['backend']]
        );
        $templateMgr->addJavaScript(
            'jatsExportSelect',
            $this->getInlineScript(),
            ['inline' => true, 'contexts' => ['backend']]
        );

        $apiKey = (string) $this->plugin->getSetting($request->getContext()->getId(), 'metaforaApiKey');
        $articles = $this->getPublishedArticles($request);
        $templateMgr->assign([
            'pageComponent' => 'Page',
            'pageTitle' => __('plugins.generic.jatsExport.displayName'),
            // full width like the DOI / dashboard pages: the article table needs the room
            'pageWidth' => TemplateManager::PAGE_WIDTH_FULL,
            'articles' => $articles,
            'filters' => $this->getFilters($articles),
            'metaforaKeyHint' => $apiKey !== '' ? '••••' . mb_substr($apiKey, -4) : '',
            'messages' => (array) $request->getSession()->pull('jatsExportMessages', []),
        ]);

        $templateMgr->display($this->plugin->getTemplateResource('jatsExport.tpl'));
    }

    /**
     * CSS for the export page, registered via addStyleSheet() so it actually
     * lands in <head> instead of being dropped from the page body.
     */
    protected function getInlineStyles(): string
    {
        return <<<CSS
			.jatsExport__intro {
				color: #777;
				font-size: 0.9375rem;
				margin: 0 0 1.25rem;
			}
			.jatsExport__toolbar {
				display: flex;
				align-items: center;
				justify-content: space-between;
				gap: 0.75rem;
				margin: 0 0 0.75rem;
				padding: 0.75rem 1rem;
				background: #eaedee;
				border-radius: 0.375rem;
			}
			.jatsExport__selectAll {
				display: flex;
				align-items: center;
				gap: 0.5rem;
				font-weight: 600;
				color: #1B3A6B;
			}
			.jatsExport__count {
				color: #777;
				font-size: 0.875rem;
			}
			.jatsExport__table {
				width: 100%;
				border-collapse: collapse;
				font-size: 0.875rem;
			}
			.jatsExport__table thead th {
				text-align: left;
				padding: 0.625rem 0.75rem;
				background: #eaedee;
				color: #1B3A6B;
				font-weight: 700;
				font-size: 0.8125rem;
				text-transform: uppercase;
				letter-spacing: 0.03em;
				border-bottom: 2px solid #ddd;
			}
			.jatsExport__table tbody td {
				padding: 0.5rem 0.75rem;
				border-bottom: 1px solid #ddd;
				vertical-align: middle;
			}
			.jatsExport__table tbody tr:hover {
				background: #f8f9fa;
			}
			.jatsExport__colCheck {
				width: 1%;
				white-space: nowrap;
			}
			.jatsExport__colId {
				width: 1%;
				white-space: nowrap;
				color: #777;
			}
			.jatsExport__footer {
				margin-top: 1rem;
				display: flex;
				flex-wrap: wrap;
				justify-content: space-between;
				gap: .75rem;
			}
			.jatsExport__footerGroup {
				display: flex;
				flex-wrap: wrap;
				align-items: center;
				gap: .5rem;
			}
			.jatsExport__footerLabel {
				color: #777;
				font-size: .875rem;
			}
			.jatsExport__message {
				margin: 0 0 .5rem;
				padding: .625rem 1rem;
				border-radius: .375rem;
				font-size: .875rem;
				border-left: 4px solid;
			}
			.jatsExport__message--ok {
				background: #e8f5ee;
				border-color: #00b24e;
			}
			.jatsExport__message--error {
				background: #fdecea;
				border-color: #d00a0a;
			}
			.jatsExport__bar {
				display: flex;
				flex-wrap: wrap;
				align-items: center;
				justify-content: space-between;
				gap: .75rem 1.5rem;
				margin: .5rem 0 1.25rem;
				padding: .75rem 1rem;
				border: 1px solid #ddd;
				border-radius: .375rem;
			}
			.jatsExport__metaforaSettings {
				display: flex;
				flex-wrap: wrap;
				align-items: center;
				gap: .5rem .75rem;
			}
			.jatsExport__filters {
				display: flex;
				flex-wrap: wrap;
				align-items: center;
				gap: .5rem;
				margin-left: auto;
			}
			.jatsExport__search {
				width: 18rem;
				max-width: 100%;
				padding: .375rem .5rem;
				border: 1px solid #bbb;
				border-radius: .25rem;
				font-size: .875rem;
			}
			.jatsExport__filterBtn {
				padding: .375rem .75rem;
				border: 1px solid #bbb;
				border-radius: .25rem;
				background: #fff;
				color: #1B3A6B;
				font-size: .875rem;
				font-weight: 600;
				cursor: pointer;
				white-space: nowrap;
			}
			.jatsExport__filterBtn[data-dropdown-toggle]::after {
				content: ' ▾';
				font-size: .75rem;
			}
			.jatsExport__filterBtn.is-active {
				background: #1B3A6B;
				border-color: #1B3A6B;
				color: #fff;
			}
			.jatsExport__dropdown {
				position: relative;
			}
			.jatsExport__menu {
				position: absolute;
				right: 0;
				top: calc(100% + .25rem);
				z-index: 20;
				min-width: 12rem;
				max-height: 22rem;
				overflow-y: auto;
				padding: .25rem 0;
				background: #fff;
				border: 1px solid #ddd;
				border-radius: .375rem;
				box-shadow: 0 4px 12px rgba(0, 0, 0, .15);
			}
			.jatsExport__menu[hidden] {
				display: none;
			}
			.jatsExport__menuItem {
				display: flex;
				justify-content: space-between;
				gap: 1rem;
				width: 100%;
				padding: .375rem .75rem;
				border: 0;
				background: none;
				color: #222;
				font-size: .875rem;
				text-align: left;
				white-space: nowrap;
				cursor: pointer;
			}
			.jatsExport__menuItem[hidden] {
				display: none;
			}
			.jatsExport__menuItem:hover,
			.jatsExport__menuItem.is-active {
				background: #eaedee;
			}
			.jatsExport__menuCount {
				color: #777;
			}
			.jatsExport__colIssue {
				width: 1%;
				white-space: nowrap;
				color: #555;
			}
			.jatsExport__pages {
				color: #777;
				font-size: .75rem;
			}
			.jatsExport__table tbody tr.jatsExport__group td {
				padding: .75rem .75rem .375rem;
				background: #f3f5f6;
				color: #1B3A6B;
				font-weight: 700;
				border-bottom: 2px solid #ddd;
			}
			.jatsExport__table tbody tr.jatsExport__group:hover {
				background: none;
			}
			.jatsExport__table tbody tr[hidden] {
				display: none;
			}
			.jatsExport__noResults td {
				color: #777;
				font-style: italic;
				text-align: center;
			}
			.jatsExport__metaforaSettingsTitle {
				font-weight: 700;
				color: #1B3A6B;
				margin-right: .5rem;
			}
			.jatsExport__metaforaKey {
				display: flex;
				align-items: center;
				gap: .5rem;
				font-size: .875rem;
			}
			.jatsExport__metaforaKey input {
				width: 22rem;
				max-width: 100%;
				padding: .375rem .5rem;
				border: 1px solid #bbb;
				border-radius: .25rem;
			}
			.jatsExport__colMetafora {
				width: 16rem;
			}
			.jatsExport__badge {
				display: inline-block;
				padding: .125rem .5rem;
				border-radius: 1rem;
				font-size: .75rem;
				font-weight: 600;
				white-space: nowrap;
			}
			.jatsExport__badge--none { background: #eaedee; color: #777; }
			.jatsExport__badge--wait { background: #fff4d6; color: #8a6100; }
			.jatsExport__badge--ok { background: #e3f0fb; color: #1B3A6B; }
			.jatsExport__badge--signed { background: #e8f5ee; color: #00753a; }
			.jatsExport__badge--error { background: #fdecea; color: #b00808; }
			.jatsExport__error {
				margin-top: .25rem;
				color: #b00808;
				font-size: .75rem;
				white-space: pre-wrap;
				max-height: 8rem;
				overflow-y: auto;
			}
			.jatsExport__rowActions {
				margin-top: .375rem;
				display: flex;
				flex-wrap: wrap;
				gap: .25rem .75rem;
			}
			.jatsExport__link:disabled {
				color: #aaa;
				cursor: not-allowed;
				text-decoration: none;
			}
			.jatsExport__link {
				padding: 0;
				border: 0;
				background: none;
				color: #006798;
				font-size: .8125rem;
				cursor: pointer;
				text-decoration: underline;
			}
			.jatsExport__origin {
				margin-top: .25rem;
				color: #777;
				font-size: .75rem;
			}
			.jatsExport__link--danger {
				color: #b00808;
			}
			.jatsExport__empty {
				color: #777;
				font-style: italic;
				margin: 0;
			}
			CSS;
    }

    /**
     * Confirmation prompts, issue/year/text filters of the article table and
     * "select all" limited to the rows currently shown.
     */
    protected function getInlineScript(): string
    {
        return <<<JS
			// Buttons that change things in Metafora ask first
			document.addEventListener('click', function (e) {
				var btn = e.target.closest && e.target.closest('[data-confirm]');
				if (btn && !window.confirm(btn.getAttribute('data-confirm'))) {
					e.preventDefault();
				}
			});

			document.addEventListener('DOMContentLoaded', function () {
				var form = document.getElementById('jatsExportForm');
				if (!form) return;
				var all = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
				var selectAll = form.querySelector('[data-select-all]');
				var rows = all('[data-article-row]', form);
				var groups = all('[data-group-row]', form);
				var countEl = form.querySelector('[data-selected-count]');
				var visibleEl = form.querySelector('[data-visible-count]');
				var noResults = form.querySelector('[data-no-results]');
				var search = document.querySelector('[data-filter-search]');
				var allBtn = document.querySelector('[data-filter-all]');
				var filter = { type: null, value: null };

				var box = function (row) { return row.querySelector('[data-article-check]'); };
				var visibleRows = function () { return rows.filter(function (r) { return !r.hidden; }); };

				function updateCount() {
					var visible = visibleRows();
					var checked = visible.filter(function (r) { return box(r).checked; }).length;
					if (countEl) countEl.textContent = checked;
					if (visibleEl) visibleEl.textContent = visible.length;
					if (selectAll) selectAll.checked = visible.length > 0 && checked === visible.length;
				}

				// Issue / year / text filter; groups without visible articles are hidden too
				function apply() {
					var text = search ? search.value.trim().toLowerCase() : '';
					rows.forEach(function (r) {
						var show = (!filter.type || r.getAttribute('data-' + filter.type) === filter.value)
							&& (!text || r.getAttribute('data-search').indexOf(text) !== -1);
						r.hidden = !show;
						// never send articles the editor can't see
						if (!show) box(r).checked = false;
					});
					groups.forEach(function (g) {
						var issue = g.getAttribute('data-issue');
						g.hidden = !rows.some(function (r) { return !r.hidden && r.getAttribute('data-issue') === issue; });
					});
					if (noResults) noResults.hidden = visibleRows().length > 0;

					if (allBtn) allBtn.classList.toggle('is-active', !filter.type);
					all('[data-filter-button]').forEach(function (b) {
						var active = b.getAttribute('data-filter-button') === filter.type;
						b.classList.toggle('is-active', active);
						b.textContent = b.getAttribute('data-default-label') + (active ? ': ' + filter.label : '');
					});
					all('[data-filter]').forEach(function (i) {
						i.classList.toggle('is-active', i.getAttribute('data-filter') === filter.type && i.getAttribute('data-value') === filter.value);
					});
					// with a year chosen, the issue menu offers only that year's issues
					all('[data-filter="issue"]').forEach(function (i) {
						i.hidden = filter.type === 'year' && i.getAttribute('data-year') !== filter.value;
					});
					updateCount();
				}

				function closeMenus(except) {
					all('.jatsExport__menu').forEach(function (m) { if (m !== except) m.hidden = true; });
				}

				all('[data-dropdown-toggle]').forEach(function (btn) {
					btn.addEventListener('click', function () {
						var menu = btn.parentNode.querySelector('.jatsExport__menu');
						closeMenus(menu);
						menu.hidden = !menu.hidden;
					});
				});
				all('[data-filter]').forEach(function (item) {
					item.addEventListener('click', function () {
						filter = { type: item.getAttribute('data-filter'), value: item.getAttribute('data-value'), label: item.getAttribute('data-label') };
						closeMenus();
						apply();
					});
				});
				if (allBtn) {
					allBtn.addEventListener('click', function () {
						filter = { type: null, value: null };
						if (search) search.value = '';
						apply();
					});
				}
				if (search) search.addEventListener('input', apply);
				document.addEventListener('click', function (e) {
					if (!e.target.closest('.jatsExport__dropdown')) closeMenus();
				});
				document.addEventListener('keydown', function (e) {
					if (e.key === 'Escape') closeMenus();
				});

				if (selectAll) {
					selectAll.addEventListener('change', function () {
						visibleRows().forEach(function (r) { box(r).checked = selectAll.checked; });
						updateCount();
					});
				}
				rows.forEach(function (r) { box(r).addEventListener('change', updateCount); });
				apply();
			});
			JS;
    }

    /**
     * Build the list of published articles for the selection table.
     */
    protected function getPublishedArticles(PKPRequest $request): array
    {
        $context = $request->getContext();
        $submissions = Repo::submission()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByStatus([Submission::STATUS_PUBLISHED])
            ->getMany();

        $articles = [];
        $issues = [];
        foreach ($submissions as $submission) {
            $publication = $submission->getCurrentPublication();
            if (!$publication) {
                continue;
            }
            $issueId = (int) $publication->getData('issueId');
            if ($issueId && !isset($issues[$issueId])) {
                $issue = Repo::issue()->get($issueId);
                $issues[$issueId] = $issue ? [
                    'id' => $issueId,
                    'year' => (int) $issue->getYear(),
                    'label' => trim($issue->getYear() . ' · № ' . ($issue->getNumber() ?: $issue->getVolume())),
                    'date' => (string) $issue->getDatePublished(),
                ] : null;
            }
            $issueData = $issues[$issueId] ?? null;
            $state = MetaforaService::state($submission);
            $articles[] = [
                'id' => $submission->getId(),
                'title' => $publication->getLocalizedFullTitle(),
                'issueId' => $issueData['id'] ?? 0,
                'issueLabel' => $issueData['label'] ?? '',
                'year' => $issueData['year'] ?? 0,
                'issueDate' => $issueData['date'] ?? '',
                'pages' => (string) $publication->getData('pages'),
                'metafora' => $state + [
                    'label' => $this->metaforaLabel($state),
                    'tone' => $this->metaforaTone($state),
                    'originLabel' => $this->metaforaOriginLabel($state),
                    'actions' => $this->metaforaActions($state),
                ],
            ];
        }

        // Newest issue first (fresh issues are the ones sent to Metafora), then in page order inside an issue
        $firstPage = fn (array $a) => preg_match('/\d+/', $a['pages'], $m) ? (int) $m[0] : PHP_INT_MAX;
        usort($articles, fn ($a, $b) => [$b['issueDate'], $b['issueId'], $firstPage($a), $a['id']]
            <=> [$a['issueDate'], $a['issueId'], $firstPage($b), $b['id']]);

        return $articles;
    }

    /**
     * Issues and years present in the article list, for the filter menus
     * (in the list's own order, i.e. newest first), with article counts.
     *
     * @return array{issues: array, years: array}
     */
    protected function getFilters(array $articles): array
    {
        $issues = $years = [];
        foreach ($articles as $article) {
            if (!$article['issueId']) {
                continue;
            }
            $issues[$article['issueId']] ??= ['id' => $article['issueId'], 'label' => $article['issueLabel'], 'year' => $article['year'], 'count' => 0];
            $issues[$article['issueId']]['count']++;
            $years[$article['year']] ??= ['year' => $article['year'], 'count' => 0];
            $years[$article['year']]['count']++;
        }
        return ['issues' => array_values($issues), 'years' => array_values($years)];
    }

    protected function metaforaLabel(array $state): string
    {
        if ($state['signedAt']) {
            return __('plugins.generic.jatsExport.metafora.status.signed', ['date' => date('d.m.Y', strtotime($state['signedAt']))]);
        }
        return match ($state['status']) {
            null => __('plugins.generic.jatsExport.metafora.status.none'),
            MetaforaService::STATUS_REJECTED => __('plugins.generic.jatsExport.metafora.status.rejected'),
            MetaforaClient::STATUS_UPLOADED => __('plugins.generic.jatsExport.metafora.status.uploaded'),
            MetaforaClient::STATUS_PROCESSING => __('plugins.generic.jatsExport.metafora.status.processing'),
            MetaforaClient::STATUS_PROCESSED => __('plugins.generic.jatsExport.metafora.status.processed'),
            MetaforaClient::STATUS_FAILED => __('plugins.generic.jatsExport.metafora.status.failed'),
            default => (string) $state['status'],
        };
    }

    /**
     * Every row shows all Metafora actions; the ones that don't apply to the
     * article's current state are disabled, with the reason as a tooltip.
     *
     * @return array<array{action: string, enabled: bool, hint: string, confirm: bool, danger: bool}>
     */
    protected function metaforaActions(array $state): array
    {
        $status = $state['status'];
        $sent = $status !== null && $status !== MetaforaService::STATUS_REJECTED;
        $processed = $status === MetaforaClient::STATUS_PROCESSED;
        $signed = (bool) $state['signedAt'];
        // Delete/resend only what OJS itself uploaded (older records have no origin yet)
        $fromOjs = !$state['origin'] || $state['origin'] === MetaforaService::ORIGIN_OJS;

        $hint = fn (string $key) => __('plugins.generic.jatsExport.metafora.disabled.' . $key);
        $why = fn (array $checks) => array_values(array_filter($checks))[0] ?? '';

        $rows = [
            'send' => $why([
                $sent && $status !== MetaforaClient::STATUS_FAILED ? $hint('alreadySent') : '',
            ]),
            'refresh' => $why([
                !$state['fileUid'] && !$state['articleUid'] ? $hint('notSent') : '',
            ]),
            'sign' => $why([
                !$sent ? $hint('notSent') : '',
                !$processed ? $hint('notProcessed') : '',
                $signed ? $hint('alreadySigned') : '',
            ]),
            'unsign' => $why([
                !$signed ? $hint('notSigned') : '',
            ]),
            'resend' => $why([
                !$sent ? $hint('notSent') : '',
                !$fromOjs ? $hint('notFromOjs') : '',
                $signed ? $hint('signed') : '',
                !$processed ? $hint('notProcessed') : '',
            ]),
            'delete' => $why([
                !$sent ? $hint('notSent') : '',
                !$fromOjs ? $hint('notFromOjs') : '',
                $signed ? $hint('signed') : '',
                !$processed ? $hint('notProcessed') : '',
            ]),
        ];

        $actions = [];
        foreach ($rows as $action => $reason) {
            $actions[] = [
                'action' => $action,
                'label' => __('plugins.generic.jatsExport.metafora.' . $action),
                'enabled' => $reason === '',
                'hint' => $reason,
                'confirm' => $action === 'refresh' ? '' : __('plugins.generic.jatsExport.metafora.confirm' . ucfirst($action)),
                'danger' => $action === 'delete',
            ];
        }
        return $actions;
    }

    /** "sent from OJS 05.10.2026" / "already in Metafora — entered by hand" */
    protected function metaforaOriginLabel(array $state): string
    {
        return match ($state['origin']) {
            MetaforaService::ORIGIN_OJS => __('plugins.generic.jatsExport.metafora.origin.ojs', [
                'date' => $state['sentAt'] ? date('d.m.Y', strtotime($state['sentAt'])) : '',
            ]),
            MetaforaService::ORIGIN_MANUAL => __('plugins.generic.jatsExport.metafora.origin.manual'),
            MetaforaService::ORIGIN_FILE => __('plugins.generic.jatsExport.metafora.origin.file'),
            default => '',
        };
    }

    /** CSS modifier for the status badge */
    protected function metaforaTone(array $state): string
    {
        if ($state['signedAt']) {
            return 'signed';
        }
        return match ($state['status']) {
            null => 'none',
            MetaforaService::STATUS_REJECTED, MetaforaClient::STATUS_FAILED => 'error',
            MetaforaClient::STATUS_PROCESSED => 'ok',
            default => 'wait',
        };
    }

    /**
     * Generate JATS XML for the selected (or all) published articles and
     * stream it back as a single .txt file.
     */
    public function export($args, $request)
    {
        if (!$request->isPost() || !$request->checkCSRF()) {
            $request->redirect(null, 'jatsExport');
            exit;
        }

        $context = $request->getContext();
        // "Select all" is handled client-side (it just checks every row's
        // box), so the selected IDs already cover the whole list when used.
        $selectedIds = array_filter(array_map('intval', (array) $request->getUserVar('submissionIds')));
        if (empty($selectedIds)) {
            $request->redirect(null, 'jatsExport');
            exit;
        }

        $collector = Repo::submission()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByStatus([Submission::STATUS_PUBLISHED])
            ->filterBySubmissionIds($selectedIds);

        $submissions = $collector->getMany();

        @set_time_limit(0);

        $acronym = preg_replace('/[^A-Za-z0-9]/', '', $context->getLocalizedAcronym()) ?: 'journal';
        $baseName = 'jats-export-' . $acronym . '-' . date('Ymd-His');

        if ($request->getUserVar('format') === 'xml') {
            $this->exportXml($submissions, $baseName);
        } else {
            $this->exportTxt($submissions, $baseName);
        }
        exit;
    }

    /**
     * Stream all selected articles' JATS XML concatenated into one .txt file.
     */
    protected function exportTxt(iterable $submissions, string $baseName): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $baseName . '.txt"');

        $fp = fopen('php://output', 'w');

        foreach ($submissions as $submission) {
            $publication = $submission->getCurrentPublication();
            if (!$publication) {
                continue;
            }

            fwrite($fp, str_repeat('=', 80) . "\n");
            fwrite($fp, 'Submission #' . $submission->getId() . ': ' . $publication->getLocalizedFullTitle() . "\n");
            fwrite($fp, str_repeat('=', 80) . "\n\n");

            try {
                fwrite($fp, $this->getJatsXml($submission, $publication));
            } catch (Throwable $e) {
                fwrite($fp, $this->describeError($e));
            }

            fwrite($fp, "\n\n");
            flush();
        }

        fclose($fp);
    }

    /**
     * Download real .xml files: a single article comes back as a plain .xml
     * (identical to the per-article "JATS XML" tab download); several are
     * packed into a .zip, one valid JATS document per file, since JATS
     * allows only one <article> root per document.
     */
    protected function exportXml(iterable $submissions, string $baseName): void
    {
        $files = [];
        $errors = [];
        foreach ($submissions as $submission) {
            $publication = $submission->getCurrentPublication();
            if (!$publication) {
                continue;
            }
            try {
                $files['article-' . $submission->getId() . '.xml'] = $this->getJatsXml($submission, $publication);
            } catch (Throwable $e) {
                $errors[] = 'Submission #' . $submission->getId() . ': ' . $publication->getLocalizedFullTitle()
                    . "\n" . $this->describeError($e);
            }
        }

        if (count($files) === 1 && empty($errors)) {
            header('Content-Type: application/xml; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . array_key_first($files) . '"');
            echo reset($files);
            return;
        }

        if (!class_exists('ZipArchive')) {
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Для выгрузки нескольких статей в XML на сервере нужно PHP-расширение zip (ZipArchive).';
            return;
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'jats');
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::OVERWRITE);
        foreach ($files as $name => $xml) {
            $zip->addFromString($name, $xml);
        }
        if (!empty($errors)) {
            $zip->addFromString('errors.txt', implode("\n\n", $errors));
        }
        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $baseName . '.zip"');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);
        unlink($zipPath);
    }

    /**
     * JATS in the format the RCSI "Metafora" system accepts (see
     * MetaforaJatsBuilder), generated from the article's OJS metadata.
     */
    protected function getJatsXml(Submission $submission, $publication): string
    {
        return (new MetaforaJatsBuilder())->build(
            Application::get()->getRequest()->getContext(),
            $submission,
            $publication
        );
    }

    /**
     * Unwrap the exception chain down to the real cause.
     */
    protected function describeError(Throwable $e): string
    {
        $real = $e;
        while ($real->getPrevious()) {
            $real = $real->getPrevious();
        }
        return '[Ошибка генерации JATS XML: ' . $real->getMessage()
            . ' (' . get_class($real) . ' в ' . $real->getFile() . ':' . $real->getLine() . ')]';
    }
}
