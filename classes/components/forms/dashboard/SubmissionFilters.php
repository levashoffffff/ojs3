<?php
/**
 * @file classes/components/form/dashboard/SubmissionFilters.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFilters
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form to add and remove filters in the submissions dashboard
 */

namespace APP\components\forms\dashboard;

use APP\facades\Repo;
use Illuminate\Support\LazyCollection;
use PKP\components\forms\dashboard\PKPSubmissionFilters;
use PKP\components\forms\FieldSelect;
use PKP\context\Context;

class SubmissionFilters extends PKPSubmissionFilters
{
    public function __construct(
        public Context $context,
        public array $userRoles,
        public LazyCollection $sections,
        public LazyCollection $categories
    ) {
        // No "days since last activity" filter: of no use for this journal's archive.
        // "Assigned to editor" is hidden for now (one editor handles everything);
        // to bring it back, call ->addAssignedTo() after ->addSectionFields().
        $this
            ->addPage(['id' => 'default', 'submitButton' => null])
            ->addGroup(['id' => 'default', 'pageId' => 'default'])
            ->addSectionFields()
            ->addIssues()
            ->addCategories()
        ;
    }

    /**
     * Year and issue as plain drop-down lists of the journal's issues
     * (the stock issue field was a type-to-search autosuggest).
     */
    protected function addIssues(): self
    {
        $issues = Repo::issue()->getCollector()
            ->filterByContextIds([$this->context->getId()])
            ->getMany()
            ->all();

        // Newest first (a future issue lands on top by its year/date)
        usort($issues, fn ($a, $b) => [(int) $b->getYear(), (string) $b->getDatePublished(), $b->getId()]
            <=> [(int) $a->getYear(), (string) $a->getDatePublished(), $a->getId()]);

        $issueOptions = [['value' => '', 'label' => __('editor.submissions.allIssues')]];
        $years = [];
        foreach ($issues as $issue) {
            $issueOptions[] = [
                'value' => $issue->getId(),
                'label' => trim($issue->getYear() . ' · № ' . ($issue->getNumber() ?: $issue->getVolume())),
            ];
            if ($issue->getYear()) {
                $years[(int) $issue->getYear()] = true;
            }
        }
        krsort($years);

        $yearOptions = [['value' => '', 'label' => __('editor.submissions.allYears')]];
        foreach (array_keys($years) as $year) {
            $yearOptions[] = ['value' => $year, 'label' => (string) $year];
        }

        return $this
            ->addField(new FieldSelect('issueYear', [
                'groupId' => 'default',
                'label' => __('issue.year'),
                'options' => $yearOptions,
                'value' => '',
            ]))
            ->addField(new FieldSelect('issueIds', [
                'groupId' => 'default',
                'label' => __('issue.issue'),
                'options' => $issueOptions,
                'value' => '',
            ]));
    }
}
