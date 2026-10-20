<?php

/**
 * @file classes/MetaforaService.php
 *
 * @class MetaforaService
 *
 * @brief Sends published articles to Metafora and keeps their Metafora state
 * (file_uid, article_uid, processing status, signature) on the submission,
 * in the metafora* properties the plugin adds to the submission schema.
 */

namespace APP\plugins\generic\jatsExport\classes;

use APP\facades\Repo;
use APP\journal\Journal;
use APP\submission\Submission;
use Throwable;

class MetaforaService
{
    /** Local status: upload was rejected right away (nothing stored in Metafora) */
    public const STATUS_REJECTED = -1;

    /** Where the Metafora record came from */
    public const ORIGIN_OJS = 'ojs';        // uploaded from here via the API
    public const ORIGIN_MANUAL = 'manual';  // entered by hand in Metafora's web form
    public const ORIGIN_FILE = 'file';      // an XML uploaded to Metafora some other way

    public function __construct(protected MetaforaClient $client, protected Journal $journal)
    {
    }

    /**
     * Upload one article (JATS XML + PDF galley). Skips articles already in
     * Metafora: either sent before from here, or found there by DOI.
     *
     * @return array{ok: bool, message: string}
     */
    public function send(Submission $submission): array
    {
        $publication = $submission->getCurrentPublication();
        $state = $this->state($submission);

        if ($state['fileUid'] && $state['status'] !== MetaforaClient::STATUS_FAILED) {
            return $this->result(false, 'уже отправлена — используйте «Переотправить», чтобы загрузить заново');
        }

        try {
            $xml = (new MetaforaJatsBuilder())->build($this->journal, $submission, $publication);
        } catch (Throwable $e) {
            return $this->fail($submission, 'не удалось сформировать XML: ' . $e->getMessage());
        }

        // Already in Metafora (e.g. entered by hand through its web form)? Link it instead of duplicating
        if (preg_match('~<article-id pub-id-type="doi">([^<]+)</article-id>~', $xml, $m)) {
            $found = $this->client->findByDoi(html_entity_decode($m[1]));
            if ($found->ok() && !empty($found->data()['article_uid'])) {
                $data = $found->data();
                $this->save($submission, [
                    'metaforaFileUid' => $data['file_uid'] ?? null,
                    'metaforaArticleUid' => $data['article_uid'],
                    'metaforaStatus' => MetaforaClient::STATUS_PROCESSED,
                    'metaforaSignedAt' => $data['signed_at'] ?? null,
                    'metaforaError' => null,
                    'metaforaSentAt' => $state['sentAt'] ?? null,
                    // No source XML in Metafora = entered by hand through its web form
                    'metaforaOrigin' => empty($data['file_uid']) ? self::ORIGIN_MANUAL : self::ORIGIN_FILE,
                ]);
                return $this->result(true, 'уже есть в «Метафоре» с этим DOI — статья привязана, повторно не отправлялась');
            }
        }

        [$pdf, $pdfError] = $this->pdf($publication);
        $response = $this->client->uploadJats(
            $xml,
            'article-' . $submission->getId() . '.xml',
            $pdf,
            $pdf !== null ? 'article-' . $submission->getId() . '.pdf' : null
        );

        if ($response->ok()) {
            $data = $response->data();
            $this->save($submission, [
                'metaforaFileUid' => $data['file_uid'] ?? null,
                'metaforaArticleUid' => null,
                'metaforaStatus' => (int) ($data['xml']['status']['code'] ?? MetaforaClient::STATUS_UPLOADED),
                'metaforaSignedAt' => null,
                'metaforaError' => null,
                'metaforaSentAt' => date('Y-m-d H:i:s'),
                'metaforaOrigin' => self::ORIGIN_OJS,
            ]);
            return $this->result(true, 'отправлена' . ($pdf === null ? ' (без PDF: ' . $pdfError . ')' : ' вместе с PDF'));
        }

        if ($response->errorCode() === 'XML_ALREADY_EXISTS' && !empty($response->data()['exists_file_uid'])) {
            $this->save($submission, [
                'metaforaFileUid' => $response->data()['exists_file_uid'],
                'metaforaStatus' => MetaforaClient::STATUS_UPLOADED,
                'metaforaError' => null,
                'metaforaOrigin' => self::ORIGIN_FILE,
            ]);
            // edit() writes a new object; re-read so refresh() sees the file_uid
            $this->refresh([Repo::submission()->get($submission->getId())]);
            return $this->result(true, 'такой XML уже был загружен — статья привязана к нему');
        }

        return $this->fail($submission, $response->errorText());
    }

    /**
     * Re-upload after changes: Metafora has no "update", so the old file is
     * deleted first. Signed publications must be unsigned beforehand.
     */
    public function resend(Submission $submission): array
    {
        $state = $this->state($submission);
        if ($guard = $this->notFromOjs($state)) {
            return $guard;
        }
        if ($state['signedAt']) {
            return $this->result(false, 'публикация подписана — сначала снимите подпись');
        }
        if ($state['fileUid'] && $state['status'] === MetaforaClient::STATUS_PROCESSED) {
            $deleted = $this->client->deleteFile($state['fileUid']);
            if (!$deleted->ok() && $deleted->status !== 404) {
                return $this->result(false, 'не удалось удалить старый файл: ' . $deleted->errorText());
            }
        }
        $this->save($submission, [
            'metaforaFileUid' => null,
            'metaforaArticleUid' => null,
            'metaforaStatus' => null,
            'metaforaSignedAt' => null,
            'metaforaError' => null,
            'metaforaOrigin' => null,
        ]);
        return $this->send(Repo::submission()->get($submission->getId()));
    }

    /**
     * Pull processing status (and the created article_uid) for every sent
     * file, then the signature status of all known publications in batches.
     *
     * @param Submission[] $submissions
     */
    public function refresh(array $submissions): array
    {
        $results = [];
        $byArticle = [];
        foreach ($submissions as $submission) {
            $state = $this->state($submission);
            if (!$state['fileUid']) {
                // Linked by DOI to a hand-entered publication: only the signature can change
                if ($state['articleUid']) {
                    $byArticle[$state['articleUid']] = $submission->getId();
                    $results[$submission->getId()] = $this->result(true, 'статус обновлён');
                }
                continue;
            }
            $response = $this->client->fileStatus($state['fileUid']);
            if (!$response->ok()) {
                $results[$submission->getId()] = $this->result(false, 'статус не получен: ' . $response->errorText());
                continue;
            }
            $data = $response->data();
            $code = (int) ($data['xml']['status']['code'] ?? 0);
            $articleUid = $data['articles'][0] ?? $state['articleUid'];
            $this->save($submission, [
                'metaforaStatus' => $code,
                'metaforaArticleUid' => $articleUid,
                'metaforaError' => $code === MetaforaClient::STATUS_FAILED
                    ? 'ошибка обработки в «Метафоре» — подробности в разделе «Файлы» на metafora.rcsi.science'
                    : null,
            ]);
            if ($articleUid) {
                $byArticle[$articleUid] = $submission->getId();
            }
            $results[$submission->getId()] = $this->result(true, 'статус обновлён');
        }

        foreach (array_chunk(array_keys($byArticle), 100) as $chunk) {
            $response = $this->client->signStatuses($chunk);
            if (!$response->ok()) {
                continue;
            }
            // This endpoint answers with a bare array, not {message, data}
            foreach ((array) $response->json as $row) {
                if (isset($row['article_uid'], $byArticle[$row['article_uid']])) {
                    $this->save(Repo::submission()->get($byArticle[$row['article_uid']]), [
                        'metaforaSignedAt' => $row['signed_at'] ?? null,
                    ]);
                }
            }
        }
        return $results;
    }

    /**
     * Remove the article from Metafora: DELETE /files/{file_uid} drops the
     * XML and the publications made from it. Metafora allows it only for a
     * processed file without signed publications.
     */
    public function delete(Submission $submission): array
    {
        $state = $this->state($submission);
        if ($guard = $this->notFromOjs($state)) {
            return $guard;
        }
        if (!$state['fileUid']) {
            return $this->result(false, 'статья не отправлялась в «Метафору» из OJS — удалять нечего');
        }
        if ($state['signedAt']) {
            return $this->result(false, 'публикация подписана — сначала снимите подпись');
        }
        $response = $this->client->deleteFile($state['fileUid']);
        // 404: already gone on Metafora's side, just forget it here too
        if (!$response->ok() && $response->status !== 404) {
            return $this->result(false, 'не удалось удалить: ' . $response->errorText());
        }
        $this->save($submission, [
            'metaforaFileUid' => null,
            'metaforaArticleUid' => null,
            'metaforaStatus' => null,
            'metaforaSignedAt' => null,
            'metaforaError' => null,
            'metaforaSentAt' => null,
            'metaforaOrigin' => null,
        ]);
        return $this->result(true, 'удалена из «Метафоры»');
    }

    public function sign(Submission $submission): array
    {
        return $this->toggleSignature($submission, true);
    }

    public function unsign(Submission $submission): array
    {
        return $this->toggleSignature($submission, false);
    }

    protected function toggleSignature(Submission $submission, bool $sign): array
    {
        $state = $this->state($submission);
        if (!$state['articleUid']) {
            return $this->result(false, 'публикация ещё не обработана «Метафорой» — обновите статус');
        }
        $response = $sign ? $this->client->sign($state['articleUid']) : $this->client->unsign($state['articleUid']);
        if (!$response->ok()) {
            return $this->result(false, $response->errorText());
        }
        $this->save($submission, ['metaforaSignedAt' => $sign ? ($response->data()['signed_at'] ?? date('c')) : null]);
        return $this->result(true, $sign ? 'подписана' : 'подпись снята');
    }

    /** @return array{fileUid: ?string, articleUid: ?string, status: ?int, signedAt: ?string, error: ?string, sentAt: ?string, origin: ?string} */
    public static function state(Submission $submission): array
    {
        $status = $submission->getData('metaforaStatus');
        return [
            'fileUid' => $submission->getData('metaforaFileUid') ?: null,
            'articleUid' => $submission->getData('metaforaArticleUid') ?: null,
            'status' => $status === null || $status === '' ? null : (int) $status,
            'signedAt' => $submission->getData('metaforaSignedAt') ?: null,
            'error' => $submission->getData('metaforaError') ?: null,
            'sentAt' => $submission->getData('metaforaSentAt') ?: null,
            'origin' => $submission->getData('metaforaOrigin') ?: null,
        ];
    }

    /** Records OJS didn't upload belong to the editors' manual work: never delete or replace them */
    protected function notFromOjs(array $state): ?array
    {
        if ($state['origin'] && $state['origin'] !== self::ORIGIN_OJS) {
            return $this->result(false, 'статья внесена в «Метафору» не из OJS — удалять или заменять её отсюда нельзя, это делает редакция в «Метафоре»');
        }
        return null;
    }

    /** PDF galley contents, or [null, reason] */
    protected function pdf($publication): array
    {
        $fileService = app()->get('file');
        $galleys = Repo::galley()->getCollector()->filterByPublicationIds([$publication->getId()])->getMany();
        foreach ($galleys as $galley) {
            $file = $galley->getData('submissionFileId') ? Repo::submissionFile()->get((int) $galley->getData('submissionFileId')) : null;
            if (!$file || $file->getData('mimetype') !== 'application/pdf') {
                continue;
            }
            try {
                return [$fileService->fs->read($fileService->get($file->getData('fileId'))->path), null];
            } catch (Throwable $e) {
                return [null, 'PDF-файл гранки не найден на диске'];
            }
        }
        return [null, 'у статьи нет PDF-гранки'];
    }

    protected function fail(Submission $submission, string $message): array
    {
        $this->save($submission, [
            'metaforaStatus' => self::STATUS_REJECTED,
            'metaforaError' => $message,
            'metaforaFileUid' => null,
            'metaforaArticleUid' => null,
        ]);
        return $this->result(false, $message);
    }

    protected function save(Submission $submission, array $props): void
    {
        Repo::submission()->edit($submission, $props);
    }

    protected function result(bool $ok, string $message): array
    {
        return ['ok' => $ok, 'message' => $message];
    }
}
