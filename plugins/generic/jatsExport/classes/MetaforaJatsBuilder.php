<?php

/**
 * @file classes/MetaforaJatsBuilder.php
 *
 * @class MetaforaJatsBuilder
 *
 * @brief Build JATS 1.4 XML for one published article in the shape expected
 * by the RCSI "Metafora" information system: bilingual titles, authors,
 * affiliations, abstracts and keywords, DOI, issue/pages and a cleaned-up
 * reference list. No copyright/license block: the editors keep it empty in Metafora.
 */

namespace APP\plugins\generic\jatsExport\classes;

use APP\core\Application;
use APP\facades\Repo;
use APP\journal\Journal;
use APP\publication\Publication;
use APP\submission\Submission;
use DOMDocument;
use DOMElement;
use PKP\facades\Locale;

class MetaforaJatsBuilder
{
    /**
     * JATS article-type values offered in the "Publication type" field
     * (labels: plugins.generic.jatsExport.pubType.<value>).
     */
    public const ARTICLE_TYPES = [
        'research-article' => true,
        'review-article' => true,
        'brief-report' => true,
        'editorial' => true,
        'letter' => true,
        'book-review' => true,
        'case-report' => true,
        'discussion' => true,
        'other' => true,
    ];
    public const DEFAULT_ARTICLE_TYPE = 'research-article';

    /** "doi: 10.24412/2413-7383-2026-1-40-241-253", "DOI 10.1000/xyz", "https://doi.org/10..." */
    protected const DOI_RE = '~(?:doi\s*:?\s*|doi\.org/)(10\.\d{4,9}/[A-Za-z0-9._;()/:\-]+)~iu';

    /** Service lines that belong to the article header, not the abstract */
    protected const ABSTRACT_JUNK_RE = '~^(УДК|UDC|Язык|Language|Ключевые слова|Key\s*words)\b~iu';

    protected DOMDocument $doc;
    protected Journal $journal;
    protected Submission $submission;
    protected Publication $publication;

    /** Locales in output order: the article's own locale first */
    protected array $locales = [];

    public function build(Journal $journal, Submission $submission, Publication $publication): string
    {
        $this->journal = $journal;
        $this->submission = $submission;
        $this->publication = $publication;

        $primary = $submission->getData('locale') ?: $journal->getPrimaryLocale();
        $this->locales = array_values(array_unique(array_merge(
            [$primary],
            array_keys(array_filter((array) $publication->getData('title'))),
            $journal->getSupportedSubmissionLocales() ?? []
        )));

        $impl = new \DOMImplementation();
        $this->doc = $impl->createDocument(
            null,
            '',
            $impl->createDocumentType(
                'article',
                '-//NLM//DTD JATS (Z39.96) Journal Archiving and Interchange DTD with OASIS Tables with MathML3 v1.4 20241031//EN',
                'https://jats.nlm.nih.gov/archiving/1.4/JATS-archive-oasis-article1-4-mathml3.dtd'
            )
        );
        $this->doc->encoding = 'UTF-8';
        $this->doc->formatOutput = true;

        $article = $this->doc->createElement('article');
        $this->doc->appendChild($article);
        $article->setAttribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');
        $article->setAttribute('xmlns:ali', 'http://www.niso.org/schemas/ali/1.0/');
        $article->setAttribute('dtd-version', '1.4');
        $type = $publication->getData('metaforaPubType');
        $article->setAttribute('article-type', isset(self::ARTICLE_TYPES[$type]) ? $type : self::DEFAULT_ARTICLE_TYPE);
        $article->setAttribute('xml:lang', $this->lang($primary));

        $front = $this->el($article, 'front');
        $this->buildJournalMeta($front);
        $this->buildArticleMeta($front);
        $this->buildBack($article);

        return $this->doc->saveXML();
    }

    protected function buildJournalMeta(DOMElement $front): void
    {
        $meta = $this->el($front, 'journal-meta');
        $titles = $this->journalTitles();
        $group = $this->el($meta, 'journal-title-group');
        $first = true;
        foreach ($titles as $locale => $title) {
            if ($first) {
                $this->el($group, 'journal-title', $title, ['xml:lang' => $this->lang($locale)]);
                $first = false;
            } else {
                $trans = $this->el($group, 'trans-title-group', null, ['xml:lang' => $this->lang($locale)]);
                $this->el($trans, 'trans-title', $title);
            }
        }
        if ($issn = $this->journal->getData('printIssn')) {
            $this->el($meta, 'issn', $issn, ['publication-format' => 'print']);
        }
        if ($issn = $this->journal->getData('onlineIssn')) {
            $this->el($meta, 'issn', $issn, ['publication-format' => 'electronic']);
        }
    }

    /**
     * The journal name is stored as "Проблемы искусственного интеллекта /
     * Problems of Artificial Intelligence ISSN 2413-7383"; split it into the
     * Russian and English titles and drop the ISSN tail.
     */
    protected function journalTitles(): array
    {
        $titles = [];
        foreach ($this->locales as $locale) {
            $name = trim((string) $this->journal->getName($locale));
            if ($name === '') {
                continue;
            }
            $parts = $this->splitBilingual($name);
            $titles[$locale] ??= $parts['ru'];
            if ($parts['en'] !== '' && ($enLocale = $this->findLocale('en'))) {
                $titles[$enLocale] ??= $parts['en'];
            }
        }
        return array_filter($titles);
    }

    /**
     * "Русское название / English name ISSN 2413-7383" -> ['ru' => ..., 'en' => ...]
     */
    protected function splitBilingual(string $value): array
    {
        $parts = array_map('trim', explode(' / ', $value, 2));
        return [
            'ru' => $this->stripIssn($parts[0]),
            'en' => isset($parts[1]) ? $this->stripIssn($parts[1]) : '',
        ];
    }

    protected function buildArticleMeta(DOMElement $front): void
    {
        $meta = $this->el($front, 'article-meta');

        if ($doi = $this->publication->getDoi() ?: $this->doiFromAbstract()) {
            $this->el($meta, 'article-id', $doi, ['pub-id-type' => 'doi']);
        } else {
            $this->el($meta, 'article-id', (string) $this->submission->getId(), ['pub-id-type' => 'publisher-id']);
        }
        if ($edn = strtoupper($this->clean($this->publication->getData('edn')))) {
            $this->el($meta, 'article-id', $edn, ['pub-id-type' => 'edn']);
        }

        $section = Repo::section()->get((int) $this->publication->getData('sectionId'));
        $udk = $this->udkCodes();
        if ($section || $udk) {
            $categories = $this->el($meta, 'article-categories');
            foreach ($this->locales as $locale) {
                $title = $section ? $this->clean($section->getData('title', $locale)) : '';
                if ($title !== '') {
                    $group = $this->el($categories, 'subj-group', null, ['xml:lang' => $this->lang($locale)]);
                    $this->el($group, 'subject', $title);
                }
            }
            if ($udk) {
                $group = $this->el($categories, 'subj-group', null, ['subj-group-type' => 'udc']);
                foreach ($udk as $code) {
                    $this->el($group, 'subject', $code);
                }
            }
        }

        $titleGroup = $this->el($meta, 'title-group');
        $first = true;
        foreach ($this->locales as $locale) {
            // A single trailing period ("…безопасности.") is not part of the title
            $title = preg_replace('/(?<!\.)\.$/u', '', $this->clean($this->publication->getLocalizedFullTitle($locale, 'text')));
            if ($title === '' || !$this->publication->getData('title', $locale)) {
                continue;
            }
            if ($first) {
                $this->el($titleGroup, 'article-title', $title, ['xml:lang' => $this->lang($locale)]);
                $first = false;
            } else {
                $trans = $this->el($titleGroup, 'trans-title-group', null, ['xml:lang' => $this->lang($locale)]);
                $this->el($trans, 'trans-title', $title);
            }
        }

        $this->buildContribGroup($meta);

        if ($date = $this->publication->getData('datePublished')) {
            $ts = strtotime($date);
            $pubDate = $this->el($meta, 'pub-date', null, ['pub-type' => 'epub', 'iso-8601-date' => date('Y-m-d', $ts)]);
            $this->el($pubDate, 'day', date('d', $ts));
            $this->el($pubDate, 'month', date('m', $ts));
            $this->el($pubDate, 'year', date('Y', $ts));
        }

        $issue = $this->publication->getData('issueId') ? Repo::issue()->get((int) $this->publication->getData('issueId')) : null;
        if ($issue && ($issueLabel = $this->issueLabel($issue->getNumber(), $issue->getVolume())) !== '') {
            $this->el($meta, 'issue', $issueLabel);
        }

        if (preg_match('/^\s*(\d+)\s*[-–—]\s*(\d+)\s*$/u', (string) $this->publication->getData('pages'), $m)) {
            $this->el($meta, 'fpage', $m[1]);
            $this->el($meta, 'lpage', $m[2]);
        } elseif (preg_match('/^\s*(\d+)\s*$/', (string) $this->publication->getData('pages'), $m)) {
            $this->el($meta, 'fpage', $m[1]);
        }

        // JATS order: …lpage, history, (permissions), self-uri…
        // No <permissions>: the editors want copyright holder, year and license left empty in Metafora
        $this->buildHistory($meta);
        $this->buildSelfUris($meta);

        $primary = $this->locales[0];
        foreach ($this->locales as $locale) {
            $paragraphs = $this->paragraphs($this->publication->getData('abstract', $locale));
            if (!$paragraphs) {
                continue;
            }
            $attrs = ['xml:lang' => $this->lang($locale)];
            if ($locale !== $primary) {
                $attrs['abstract-type'] = 'summary';
            }
            $abstract = $this->el($meta, 'abstract', null, $attrs);
            foreach ($paragraphs as $p) {
                $this->el($abstract, 'p', $p);
            }
        }

        $keywords = (array) $this->publication->getData('keywords');
        foreach ($this->locales as $locale) {
            $words = array_filter(array_map(
                fn ($k) => $this->clean(is_array($k) ? ($k['name'] ?? '') : $k),
                (array) ($keywords[$locale] ?? [])
            ));
            if ($words) {
                $group = $this->el($meta, 'kwd-group', null, ['xml:lang' => $this->lang($locale)]);
                foreach ($words as $word) {
                    $this->el($group, 'kwd', $word);
                }
            }
        }

        // JATS order: …kwd-group, funding-group
        $funding = $this->perLocale('funding');
        if ($funding) {
            $group = $this->el($meta, 'funding-group');
            foreach ($funding as $locale => $text) {
                $this->el($group, 'funding-statement', $text, ['xml:lang' => $this->lang($locale)]);
            }
        }
    }

    /** Dates the manuscript was received and accepted (plugin fields) */
    protected function buildHistory(DOMElement $meta): void
    {
        $dates = array_filter([
            'received' => $this->publication->getData('dateReceived'),
            'accepted' => $this->publication->getData('dateAccepted'),
        ], fn ($d) => $d && strtotime($d));
        if (!$dates) {
            return;
        }
        $history = $this->el($meta, 'history');
        foreach ($dates as $type => $date) {
            $ts = strtotime($date);
            $el = $this->el($history, 'date', null, ['date-type' => $type, 'iso-8601-date' => date('Y-m-d', $ts)]);
            $this->el($el, 'day', date('d', $ts));
            $this->el($el, 'month', date('m', $ts));
            $this->el($el, 'year', date('Y', $ts));
        }
    }

    /** UDC codes from the plugin field, one per line or separated by ";" (commas/colons belong to a code) */
    protected function udkCodes(): array
    {
        $raw = preg_replace('/^\s*(УДК|UDC|UDK)\s*:?\s*/iu', '', (string) $this->publication->getData('udk'));
        return array_values(array_filter(array_map('trim', preg_split('/\s*[;\n]\s*/u', $raw))));
    }

    /** Non-empty values of a multilingual publication field, in output locale order */
    protected function perLocale(string $prop): array
    {
        $values = [];
        foreach ($this->locales as $locale) {
            $text = $this->clean($this->publication->getData($prop, $locale));
            if ($text !== '') {
                $values[$locale] = $text;
            }
        }
        return $values;
    }

    protected function buildContribGroup(DOMElement $meta): void
    {
        $authors = $this->publication->getData('authors');
        if (!$authors || !count($authors)) {
            return;
        }

        $group = $this->el($meta, 'contrib-group');
        // Affiliation key => [locale => ['id' => affN, 'name' => ..., 'country' => ...]]
        $affiliations = [];
        $nextAffId = 1;

        foreach ($authors as $author) {
            // Authors keep the OJS order: the first one is the lead author, as in Metafora
            $contrib = $this->el($group, 'contrib', null, ['contrib-type' => $this->contribType($author)]);

            $names = $this->el($contrib, 'name-alternatives');
            foreach ($this->locales as $locale) {
                $family = $this->clean($author->getFamilyName($locale));
                $given = $this->clean($author->getGivenName($locale));
                if ($family === '' && $given === '') {
                    continue;
                }
                $name = $this->el($names, 'name', null, ['xml:lang' => $this->lang($locale)]);
                if ($family !== '') {
                    $this->el($name, 'surname', $family);
                }
                if ($given !== '') {
                    $this->el($name, 'given-names', $given);
                }
            }

            foreach ($author->getAffiliations() as $affiliation) {
                $perLocale = [];
                foreach ($this->locales as $locale) {
                    $affName = $this->clean($affiliation->getAffiliationName($locale));
                    if ($affName !== '') {
                        $perLocale[$locale] = $affName;
                    }
                }
                if (!$perLocale) {
                    continue;
                }
                $key = mb_strtolower(reset($perLocale));
                if (!isset($affiliations[$key])) {
                    foreach ($perLocale as $locale => $affName) {
                        [$institution, $city] = self::splitAffiliation($affName);
                        $affiliations[$key][$locale] = [
                            'id' => 'aff' . $nextAffId++,
                            'name' => $institution,
                            // The author's "City" field (plugin) wins over one parsed from the address
                            'city' => $this->clean($author->getData('city', $locale)) ?: $city,
                            'country' => $this->countryName($author->getCountry(), $locale),
                        ];
                    }
                }
                foreach ($affiliations[$key] as $aff) {
                    $this->el($contrib, 'xref', null, ['ref-type' => 'aff', 'rid' => $aff['id']]);
                }
            }

            $email = trim((string) $author->getEmail());
            // Imported articles carry a nomail@... placeholder instead of a real address
            if ($email !== '' && !preg_match('/^nomail@/i', $email)) {
                $this->el($contrib, 'email', $email);
            }

            if ($orcid = trim((string) $author->getOrcid())) {
                if (!str_starts_with($orcid, 'http')) {
                    $orcid = 'https://orcid.org/' . $orcid;
                }
                $this->el($contrib, 'contrib-id', $orcid, ['contrib-id-type' => 'orcid']);
            }
            foreach (['spin' => 'spin', 'scopusId' => 'scopus', 'researcherId' => 'researcherid'] as $prop => $type) {
                if ($id = trim((string) $author->getData($prop))) {
                    $this->el($contrib, 'contrib-id', $id, ['contrib-id-type' => $type]);
                }
            }
        }

        foreach ($affiliations as $perLocale) {
            foreach ($perLocale as $locale => $aff) {
                $alternatives = $this->el($group, 'aff-alternatives', null, ['id' => $aff['id']]);
                $affEl = $this->el($alternatives, 'aff');
                $lang = ['xml:lang' => $this->lang($locale)];
                $this->el($affEl, 'institution', $aff['name'], $lang);
                if ($aff['city']) {
                    $this->el($affEl, 'city', $aff['city'], $lang);
                }
                if ($aff['country']) {
                    $this->el($affEl, 'country', $aff['country'], $lang);
                }
            }
        }
    }

    /**
     * Affiliations are entered as one line with the postal address:
     * "ФГБОУ ВО «Донецкий государственный университет», г. Донецк 28301, …, ул. …".
     * Keep the organisation name as the institution and pull out the city.
     *
     * @return array{0: string, 1: string} [institution, city]
     */
    public static function splitAffiliation(string $value): array
    {
        $city = '([А-ЯЁ][а-яё]+(?:[\s-][А-ЯЁ][а-яё]+)*)';
        if (preg_match('/^(.+?),\s*г\.\s*' . $city . '/u', $value, $m)) {
            // "…университет 275500, г. Геническ": drop a postal code left at the end of the name
            return [trim(preg_replace('/[\s,]+\d{5,6}$/u', '', $m[1]), " ,"), $m[2]];
        }
        // "…Федерации 125167, Москва, Ленинградский пр-кт…": the postal code starts the address
        if (preg_match('/^(.+?)[\s,]+\d{6}\b(.*)$/u', $value, $m)) {
            preg_match('/г\.\s*' . $city . '/u', $m[2], $c) || preg_match('/^\s*,\s*' . $city . '\s*(?:,|$)/u', $m[2], $c);
            return [trim($m[1], " ,"), $c[1] ?? ''];
        }
        // English form: "Donetsk State University, Donetsk, …" is too ambiguous to split
        return [$value, ''];
    }

    /**
     * "Contributor role" of the author in OJS (Author / Translator user
     * group). Groups are stored by their translated names, so match on those.
     */
    protected function contribType($author): string
    {
        $group = $author->getUserGroup();
        if (!$group) {
            return 'author';
        }
        $names = [];
        // UserGroup is an Eloquent model: multilingual settings come as [locale => text]
        foreach (['name', 'abbrev'] as $prop) {
            $names = array_merge($names, array_values((array) $group->getAttribute($prop)));
        }
        return preg_match('/translat|перевод/iu', implode(' ', $names)) ? 'translator' : 'author';
    }

    protected function buildSelfUris(DOMElement $meta): void
    {
        $request = Application::get()->getRequest();
        $dispatcher = $request->getDispatcher();
        $bestId = $this->submission->getBestId();

        $this->el($meta, 'self-uri', null, [
            'xlink:href' => $dispatcher->url($request, Application::ROUTE_PAGE, $this->journal->getPath(), 'article', 'view', [$bestId], urlLocaleForPage: ''),
        ]);

        $galleys = Repo::galley()->getCollector()
            ->filterByPublicationIds([$this->publication->getId()])
            ->getMany();
        foreach ($galleys as $galley) {
            $file = $galley->getData('submissionFileId') ? Repo::submissionFile()->get((int) $galley->getData('submissionFileId')) : null;
            if (!$file || $file->getData('mimetype') !== 'application/pdf') {
                continue;
            }
            $this->el($meta, 'self-uri', null, [
                'content-type' => 'pdf',
                'xlink:href' => $dispatcher->url($request, Application::ROUTE_PAGE, $this->journal->getPath(), 'article', 'download', [$bestId, $galley->getBestGalleyId()], urlLocaleForPage: ''),
                'xlink:title' => 'PDF',
            ]);
            break;
        }
    }

    protected function buildBack(DOMElement $article): void
    {
        // The article's own list first, then the English "References" list
        // (plugin field citationsEn), numbered on as ref21, ref22… like Metafora does
        $lists = [
            $this->lang($this->locales[0]) => $this->references(),
            'en' => self::normalizeReferences(preg_split('/\R/u', (string) $this->publication->getData('citationsEn'))),
        ];
        $acknowledgments = $this->perLocale('acknowledgments');
        if (!array_filter($lists) && !$acknowledgments) {
            return;
        }
        $back = $this->el($article, 'back');
        // JATS order inside <back>: ack before ref-list
        foreach ($acknowledgments as $locale => $text) {
            $ack = $this->el($back, 'ack', null, ['xml:lang' => $this->lang($locale)]);
            $this->el($ack, 'p', $text);
        }
        if (!array_filter($lists)) {
            return;
        }
        $list = $this->el($back, 'ref-list');
        $n = 0;
        foreach ($lists as $lang => $references) {
            foreach ($references as $text) {
                $ref = $this->el($list, 'ref', null, ['id' => 'ref' . ++$n]);
                $this->el($ref, 'mixed-citation', $text, ['publication-type' => 'other', 'xml:lang' => $lang]);
            }
        }
    }

    /**
     * References are stored one line per citation, but most were pasted from
     * PDFs, so a single source is often broken across several lines
     * ("1. Го Т. ... jumping" / "wheel legged robot ..." / "doi:..."). Glue
     * continuation lines back onto their numbered source, drop the leading
     * numbers and repair hyphens lost in copy-paste.
     */
    public function references(): array
    {
        $lines = [];
        // A LazyCollection in OJS 3.5: iterate it as is, an (array) cast
        // would expose its internal source closure instead of the citations
        foreach ($this->publication->getData('citations') ?? [] as $citation) {
            $lines[] = is_object($citation) && method_exists($citation, 'getRawCitation')
                ? $citation->getRawCitation()
                : (string) $citation;
        }
        if (!$lines && ($raw = $this->publication->getData('citationsRaw'))) {
            $lines = preg_split('/\R/u', $raw);
        }
        return self::normalizeReferences($lines);
    }

    public static function normalizeReferences(array $lines): array
    {
        $lines = array_values(array_filter(array_map(
            fn ($l) => trim(preg_replace('/[\s\x{00A0}]+/u', ' ', html_entity_decode(strip_tags((string) $l), ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
            $lines
        ), fn ($l) => $l !== ''));

        $numberRe = '/^(\d{1,3})\s*[.)]\s*(.*)$/u';
        $isNumbered = false;
        foreach ($lines as $line) {
            if (preg_match($numberRe, $line, $m) && (int) $m[1] === 1) {
                $isNumbered = true;
                break;
            }
        }

        $refs = [];
        $expected = 1;
        foreach ($lines as $line) {
            if (!$isNumbered) {
                $refs[] = $line;
                continue;
            }
            // Only the next number in sequence starts a new source, so a
            // continuation line that happens to begin with "2021." is kept.
            if (preg_match($numberRe, $line, $m) && (int) $m[1] === $expected) {
                $refs[] = $m[2];
                $expected++;
            } elseif (!$refs) {
                $refs[] = $line;
            } else {
                $last = array_key_last($refs);
                $refs[$last] .= (preg_match('/[-\/]$/u', $refs[$last]) ? '' : ' ') . $line;
            }
        }

        return array_values(array_filter(array_map([self::class, 'repairReference'], $refs)));
    }

    /** Restore hyphens and dashes that were lost when copying from PDF */
    protected static function repairReference(string $ref): string
    {
        $fixes = [
            '/(\d) (е|я|й|го|м) (изд|ред|доп)/u' => '$1-$2 $3',  // "2 е изд" -> "2-е изд"
            '/\bун т(\b|а\b|ом\b)/u' => 'ун-т$1',               // "ун т" -> "ун-т"
            '/\b(Изд|изд) во\b/u' => '$1-во',                    // "Изд во" -> "Изд-во"
            '/\bБХВ Петербург\b/u' => 'БХВ-Петербург',
            '/\bСанкт Петербург\b/u' => 'Санкт-Петербург',
            '/\bлаборант исследователь\b/u' => 'лаборант-исследователь',
            '/(\d)_(\d)/u' => '$1–$2',                           // "5_7 Apr" -> "5–7 Apr"
            '~(https?://)\s+~u' => '$1',                         // "https:// site.ru" -> "https://site.ru"
            '/\s+([,.;:])/u' => '$1',                            // no space before punctuation
            '~(?<!:)//(?=\S)~u' => '// ',                        // "//Proceedings" -> "// Proceedings", but not "https://"
            '/\s{2,}/u' => ' ',
        ];
        return trim(preg_replace(array_keys($fixes), array_values($fixes), $ref));
    }

    /**
     * Until DOIs are assigned through OJS's DOI section, some articles only
     * mention theirs inside the abstract text ("doi: 10.24412/...").
     */
    protected function doiFromAbstract(): ?string
    {
        foreach ((array) $this->publication->getData('abstract') as $abstract) {
            if (preg_match(self::DOI_RE, $this->clean($abstract), $m)) {
                return rtrim($m[1], '.,;');
            }
        }
        return null;
    }

    protected function issueLabel($number, $volume): string
    {
        $number = trim((string) $number);
        $volume = trim((string) $volume);
        if ($number !== '' && str_contains($number, '(')) {
            return $number;
        }
        if ($number !== '' && $volume !== '') {
            return "{$number}({$volume})";
        }
        return $number !== '' ? $number : $volume;
    }

    protected function countryName(?string $code, string $locale): string
    {
        if (!$code) {
            return '';
        }
        $country = Locale::getCountries($locale)->getByAlpha2($code);
        return $country ? $country->getLocalName() : '';
    }

    /** Split HTML text into clean paragraphs */
    protected function paragraphs(?string $html): array
    {
        if (!$html) {
            return [];
        }
        $parts = preg_split('~</p>|<br\s*/?>~i', $html);
        // A DOI written into the abstract goes to <article-id>, not the text
        $parts = array_map(
            fn ($p) => trim(preg_replace(self::DOI_RE, '', $this->clean($p)), " \t,;"),
            $parts
        );
        return array_values(array_filter(
            $parts,
            fn ($p) => $p !== '' && $p !== '.' && !preg_match(self::ABSTRACT_JUNK_RE, $p)
        ));
    }

    protected function clean($value): string
    {
        // Block-level breaks become spaces so "…239</p><p>Язык" doesn't glue together
        $text = preg_replace('~<br\s*/?>|</(p|div|li|h\d)>~i', ' ', (string) $value);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text));
        return $text;
    }

    protected function stripIssn(string $title): string
    {
        return trim(preg_replace('/\s*ISSN\s*[\dXx-]+\s*$/u', '', $title));
    }

    protected function findLocale(string $lang): ?string
    {
        foreach ($this->locales as $locale) {
            if ($this->lang($locale) === $lang) {
                return $locale;
            }
        }
        return null;
    }

    /** "ru_RU" / "ru" / "sr@latin" -> "ru" */
    protected function lang(string $locale): string
    {
        return strtolower(preg_split('/[_@-]/', $locale)[0]);
    }

    protected function el(DOMElement $parent, string $name, ?string $text = null, array $attrs = []): DOMElement
    {
        $el = $this->doc->createElement($name);
        $parent->appendChild($el);
        foreach ($attrs as $attr => $value) {
            $el->setAttribute($attr, (string) $value);
        }
        if ($text !== null) {
            $el->appendChild($this->doc->createTextNode($text));
        }
        return $el;
    }
}
