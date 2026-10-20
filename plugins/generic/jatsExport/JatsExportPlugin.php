<?php

/**
 * @file JatsExportPlugin.php
 *
 * @class JatsExportPlugin
 *
 * @brief Lets journal managers pick any number of published articles and
 *  download their JATS XML (generated the same way as the per-article
 *  "JATS XML" tab) concatenated into a single .txt file.
 */

namespace APP\plugins\generic\jatsExport;

use APP\core\Application;
use APP\facades\Repo;
use APP\components\forms\publication\IssueEntryForm;
use APP\plugins\generic\jatsExport\classes\MetaforaJatsBuilder;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FieldText;
use PKP\components\forms\FieldTextarea;
use PKP\components\forms\FormComponent;
use PKP\components\forms\publication\ContributorForm;
use PKP\components\forms\publication\PKPCitationsForm;
use PKP\components\forms\publication\PKPMetadataForm;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\RedirectAction;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

class JatsExportPlugin extends GenericPlugin
{
    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName()
    {
        return __('plugins.generic.jatsExport.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription()
    {
        return __('plugins.generic.jatsExport.description');
    }

    /**
     * @copydoc Plugin::register()
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null)
    {
        if (parent::register($category, $path, $mainContextId)) {
            if ($this->getEnabled($mainContextId)) {
                Hook::add('LoadHandler', $this->callbackLoadHandler(...));
                // Extra metadata the Metafora JATS needs but OJS has no field for
                Hook::add('Schema::get::publication', $this->addPublicationSchema(...));
                Hook::add('Schema::get::author', $this->addAuthorSchema(...));
                Hook::add('Schema::get::submission', $this->addSubmissionSchema(...));
                Hook::add('Form::config::before', $this->addFormFields(...));
                // "Author 1, 2, 3…" position chosen in the contributor form
                Hook::add('Author::add::before', $this->takeAuthorPosition(...));
                Hook::add('Author::add', $this->applyAuthorPositionAfterAdd(...));
                Hook::add('Author::edit', $this->applyAuthorPositionOnEdit(...));
            }
            return true;
        }
        return false;
    }

    /**
     * Publication metadata Metafora needs but OJS has no field for:
     * English references, article type, UDC, EDN, received/accepted dates,
     * funding and acknowledgements.
     */
    public function addPublicationSchema(string $hookName, array $args): bool
    {
        $schema = $args[0];
        $string = fn (array $extra = []) => (object) ([
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ] + $extra);

        $schema->properties->citationsEn = $string();
        $schema->properties->metaforaPubType = $string();
        $schema->properties->udk = $string();
        $schema->properties->edn = $string();
        $schema->properties->dateReceived = $string(['validation' => ['nullable', 'date_format:Y-m-d']]);
        $schema->properties->dateAccepted = $string(['validation' => ['nullable', 'date_format:Y-m-d']]);
        $schema->properties->funding = $string(['multilingual' => true]);
        $schema->properties->acknowledgments = $string(['multilingual' => true]);
        return false;
    }

    /**
     * Author's city (per language) for <aff><city>, e.g. Москва / Moscow,
     * plus the researcher IDs Metafora stores besides ORCID.
     */
    public function addAuthorSchema(string $hookName, array $args): bool
    {
        $schema = $args[0];
        $schema->properties->city = (object) [
            'type' => 'string',
            'multilingual' => true,
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];
        foreach (['spin', 'scopusId', 'researcherId'] as $prop) {
            $schema->properties->{$prop} = (object) [
                'type' => 'string',
                'apiSummary' => true,
                'validation' => ['nullable'],
            ];
        }
        // Carries the typed ORCID past the API (stored into `orcid`, see storeOrcid())
        $schema->properties->orcidId = (object) [
            'type' => 'string',
            'validation' => ['nullable', 'regex:' . self::ORCID_RE],
        ];
        // Only carries the chosen position through the API; turned into `seq`
        // by the Author hooks below and never stored
        $schema->properties->authorPosition = (object) [
            'type' => 'integer',
            'validation' => ['nullable', 'min:1'],
        ];
        return false;
    }

    /** Position requested for an author being added, until it has an id */
    protected ?int $pendingAuthorPosition = null;

    /** ORCID iD, bare ("0000-0003-4018-234X") or as an https://orcid.org/ link */
    protected const ORCID_RE = '~^\s*(?:https?://(?:www\.)?orcid\.org/)?(\d{4}-\d{4}-\d{4}-\d{3}[\dX])\s*$~i';

    /**
     * Without the ORCID API integration OJS hides its ORCID field and its
     * contributor API refuses an "orcid" param (it expects verified iDs).
     * The plugin's "orcidId" field carries the value instead; store it in
     * the regular orcid property as the https://orcid.org/ URL OJS uses.
     */
    protected function storeOrcid($author): void
    {
        $value = $author->getData('orcidId');
        if ($value === null) {
            return;
        }
        $author->setData('orcidId', null);
        if (trim((string) $value) === '') {
            $author->setData('orcid', null);
        } elseif (preg_match(self::ORCID_RE, (string) $value, $m)) {
            $author->setData('orcid', 'https://orcid.org/' . strtoupper($m[1]));
        }
    }

    public function takeAuthorPosition(string $hookName, array $args): bool
    {
        $author = $args[0];
        $this->storeOrcid($author);
        $this->pendingAuthorPosition = $author->getData('authorPosition') ? (int) $author->getData('authorPosition') : null;
        $author->setData('authorPosition', null);
        return false;
    }

    public function applyAuthorPositionAfterAdd(string $hookName, array $args): bool
    {
        if ($this->pendingAuthorPosition !== null) {
            $author = $args[0];
            $this->reorderAuthors((int) $author->getData('publicationId'), $author->getId(), $this->pendingAuthorPosition);
            $this->pendingAuthorPosition = null;
        }
        return false;
    }

    public function applyAuthorPositionOnEdit(string $hookName, array $args): bool
    {
        [$newAuthor] = $args;
        $this->storeOrcid($newAuthor);
        $position = $newAuthor->getData('authorPosition');
        $newAuthor->setData('authorPosition', null);
        if ($position) {
            // The edited author itself is saved right after this hook, with this seq
            $seq = $this->reorderAuthors((int) $newAuthor->getData('publicationId'), $newAuthor->getId(), (int) $position);
            $newAuthor->setData('seq', $seq);
        }
        return false;
    }

    /**
     * Put one author at a 1-based position and renumber the publication's
     * authors 0…n-1 in that order (same `seq` the "Order" button writes).
     *
     * @return int The seq given to $authorId
     */
    protected function reorderAuthors(int $publicationId, int $authorId, int $position): int
    {
        $authors = Repo::author()->getCollector()
            ->filterByPublicationIds([$publicationId])
            ->orderBy(\PKP\author\Collector::ORDERBY_SEQUENCE)
            ->getMany()
            ->values()
            ->all();

        $others = array_values(array_filter($authors, fn ($a) => $a->getId() !== $authorId));
        $index = max(0, min($position - 1, count($others)));

        $seq = 0;
        foreach ($others as $i => $other) {
            if ($i === $index) {
                $seq++; // slot taken by the moved author
            }
            if ((int) $other->getData('seq') !== $seq) {
                $other->setData('seq', $seq);
                Repo::author()->dao->update($other);
            }
            $seq++;
        }

        $moved = array_values(array_filter($authors, fn ($a) => $a->getId() === $authorId))[0] ?? null;
        if ($moved && (int) $moved->getData('seq') !== $index) {
            $moved->setData('seq', $index);
            $moved->setData('authorPosition', null);
            Repo::author()->dao->update($moved);
        }
        return $index;
    }

    /**
     * Where the article stands in Metafora (see MetaforaService). Not shown
     * in any form; written only by the export page.
     */
    public function addSubmissionSchema(string $hookName, array $args): bool
    {
        $schema = $args[0];
        foreach (['metaforaFileUid', 'metaforaArticleUid', 'metaforaSignedAt', 'metaforaError', 'metaforaSentAt', 'metaforaOrigin'] as $prop) {
            $schema->properties->{$prop} = (object) ['type' => 'string', 'validation' => ['nullable']];
        }
        $schema->properties->metaforaStatus = (object) ['type' => 'integer', 'validation' => ['nullable']];
        return false;
    }

    /**
     * Show the extra fields in the publication forms ("References",
     * "Metadata") and in the contributor (author) form.
     */
    public function addFormFields(string $hookName, FormComponent $form): bool
    {
        // Hook::run() spreads its arguments, so the form arrives as-is (not in an array)
        $has = fn (string $name) => (bool) array_filter($form->fields, fn ($f) => $f->name === $name);
        // Publication forms don't keep their publication, but the API action URL ends in /publications/{id}
        $publication = fn () => preg_match('~/publications/(\d+)~', (string) $form->action, $m)
            ? Repo::publication()->get((int) $m[1])
            : null;

        if ($form->id === PKPCitationsForm::FORM_CITATIONS && !$has('citationsEn')) {
            $form->addField(new FieldTextarea('citationsEn', [
                'label' => __('plugins.generic.jatsExport.citationsEn'),
                'description' => __('plugins.generic.jatsExport.citationsEn.description'),
                'value' => (string) $publication()?->getData('citationsEn'),
            ]));
        }

        if ($form->id === PKPMetadataForm::FORM_METADATA && !$has('metaforaPubType')) {
            $this->addMetadataFields($form, $publication());
        }

        // The native "Pages" field has no hint; JATS needs "first-last" to fill fpage/lpage
        if ($form->id === IssueEntryForm::FORM_ISSUE_ENTRY) {
            foreach ($form->fields as $field) {
                if ($field->name === 'pages' && empty($field->description)) {
                    $field->description = __('plugins.generic.jatsExport.pages.description');
                }
            }
        }

        if ($form->id === ContributorForm::FORM_CONTRIBUTOR && !$has('city')) {
            $form->addField(new FieldText('city', [
                'label' => __('plugins.generic.jatsExport.city'),
                'description' => __('plugins.generic.jatsExport.city.description'),
                'isMultilingual' => true,
            ]), [FIELD_POSITION_AFTER, 'country']);
            // OJS shows ORCID only with the ORCID API integration configured;
            // without it, offer a plain field (the ID itself is all Metafora needs)
            if (!$has('orcid')) {
                // Named orcidId: the contributor API drops an "orcid" param (see storeOrcid())
                $form->addField(new FieldText('orcidId', [
                    'label' => 'ORCID',
                    'description' => __('plugins.generic.jatsExport.orcid.description'),
                ]), $has('url') ? [FIELD_POSITION_AFTER, 'url'] : [FIELD_POSITION_AFTER, 'email']);
            }
            foreach (['researcherId', 'scopusId', 'spin'] as $prop) {
                // Each inserted right after ORCID, so the final order is SPIN, Scopus, ResearcherID
                $form->addField(new FieldText($prop, [
                    'label' => __('plugins.generic.jatsExport.author.' . $prop),
                ]), $has('orcid') ? [FIELD_POSITION_AFTER, 'orcid'] : ($has('orcidId') ? [FIELD_POSITION_AFTER, 'orcidId'] : []));
            }
        }

        // Last, so a problem here can't keep the fields above from showing
        if ($form->id === ContributorForm::FORM_CONTRIBUTOR && !$has('authorPosition')) {
            $this->addAuthorPositionField($form);
        }

        return false;
    }

    /**
     * Contributor form: "Author 1 / 2 / 3…" position list instead of the
     * Author/Translator role choice (every contributor is an author here,
     * as in Metafora). The UI preselects the author's current position.
     */
    protected function addAuthorPositionField(FormComponent $form): void
    {
        foreach ($form->fields as $i => $field) {
            if ($field->name === 'userGroupId' && !empty($field->options)) {
                // ContributorForm passes the options as a Laravel Collection
                $roleOptions = is_array($field->options) ? $field->options : $field->options->all();
                $author = array_values(array_filter(
                    $roleOptions,
                    fn ($o) => !preg_match('/translat|перевод/iu', (string) ($o['label'] ?? ''))
                ))[0] ?? $roleOptions[0];
                unset($form->fields[$i]);
                $form->fields = array_values($form->fields);
                $form->addHiddenField('userGroupId', $author['value']);
                break;
            }
        }

        // Action: …/publications/{id}/contributors; one extra slot for a new author
        $count = preg_match('~/publications/(\d+)~', (string) $form->action, $m)
            ? Repo::author()->getCollector()->filterByPublicationIds([(int) $m[1]])->getCount()
            : 0;
        $options = [];
        for ($n = 1; $n <= $count + 1; $n++) {
            $options[] = ['value' => $n, 'label' => __('plugins.generic.jatsExport.authorPosition.option', ['n' => $n])];
        }
        $form->addField(new FieldSelect('authorPosition', [
            'label' => __('plugins.generic.jatsExport.authorPosition'),
            'description' => __('plugins.generic.jatsExport.authorPosition.description'),
            'options' => $options,
            'value' => $count + 1,
        ]), [FIELD_POSITION_BEFORE, 'familyName']);
    }

    /**
     * "For Metafora" block at the end of the publication Metadata form.
     */
    protected function addMetadataFields(FormComponent $form, $publication): void
    {
        $locales = array_column($form->locales, 'key');
        $multilingual = fn (string $prop) => array_combine(
            $locales,
            array_map(fn ($l) => (string) $publication?->getData($prop, $l), $locales)
        );

        $form->addField(new FieldSelect('metaforaPubType', [
            'label' => __('plugins.generic.jatsExport.pubType'),
            'description' => __('plugins.generic.jatsExport.pubType.description'),
            'options' => array_map(
                fn ($value) => ['value' => $value, 'label' => __('plugins.generic.jatsExport.pubType.' . $value)],
                array_keys(MetaforaJatsBuilder::ARTICLE_TYPES)
            ),
            'value' => $publication?->getData('metaforaPubType') ?: MetaforaJatsBuilder::DEFAULT_ARTICLE_TYPE,
        ]))
            ->addField(new FieldText('udk', [
                'label' => __('plugins.generic.jatsExport.udk'),
                'description' => __('plugins.generic.jatsExport.udk.description'),
                'value' => (string) $publication?->getData('udk'),
            ]))
            ->addField(new FieldText('edn', [
                'label' => 'EDN',
                'description' => __('plugins.generic.jatsExport.edn.description'),
                'value' => (string) $publication?->getData('edn'),
            ]))
            ->addField(new FieldText('dateReceived', [
                'label' => __('plugins.generic.jatsExport.dateReceived'),
                'inputType' => 'date',
                'value' => (string) $publication?->getData('dateReceived'),
            ]))
            ->addField(new FieldText('dateAccepted', [
                'label' => __('plugins.generic.jatsExport.dateAccepted'),
                'inputType' => 'date',
                'value' => (string) $publication?->getData('dateAccepted'),
            ]))
            ->addField(new FieldTextarea('funding', [
                'label' => __('plugins.generic.jatsExport.funding'),
                'description' => __('plugins.generic.jatsExport.funding.description'),
                'isMultilingual' => true,
                'value' => $multilingual('funding'),
            ]))
            ->addField(new FieldTextarea('acknowledgments', [
                'label' => __('plugins.generic.jatsExport.acknowledgments'),
                'isMultilingual' => true,
                'value' => $multilingual('acknowledgments'),
            ]));
    }

    /**
     * Route requests for the plugin's own page to our handler.
     *
     * @param string $hookName
     * @param array $args
     */
    public function callbackLoadHandler($hookName, $args)
    {
        $page = &$args[0];
        $handler = &$args[3];

        if ($page === 'jatsExport') {
            $handler = new JatsExportHandler($this);
            return true;
        }
        return false;
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $actionArgs)
    {
        $dispatcher = $request->getDispatcher();
        return array_merge(
            $this->getEnabled() ? [
                new LinkAction(
                    'manage',
                    new RedirectAction($dispatcher->url(
                        $request,
                        Application::ROUTE_PAGE,
                        null,
                        'jatsExport'
                    )),
                    __('plugins.generic.jatsExport.manage'),
                    null
                ),
            ] : [],
            parent::getActions($request, $actionArgs)
        );
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\jatsExport\JatsExportPlugin', '\JatsExportPlugin');
}
