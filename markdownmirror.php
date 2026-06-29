<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.markdownmirror
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use League\HTMLToMarkdown\HtmlConverter;

require_once __DIR__ . '/vendor/autoload.php';

class PlgSystemMarkdownmirror extends CMSPlugin
{
    public function onAfterRender(): void
    {
        $app = Factory::getApplication();

        if (!$app->isClient('site')) {
            return;
        }

        if ((int) $app->input->getInt('md', 0) !== 1) {
            return;
        }

        $html = $app->getBody();

        $meta        = $this->extractMeta($html);
        $contentHtml = $this->extractContent($html);
        $markdown    = $this->toMarkdown($contentHtml);
        $output      = $this->buildFrontmatter($meta) . "\n\n" . $markdown;

        $app->setBody($output);

        if (!headers_sent()) {
            header('Content-Type: text/markdown; charset=utf-8');
            header('X-Robots-Tag: noindex, follow');
        }
    }

    private function extractMeta(string $html): array
    {
        $dom   = $this->parseDom($html);
        $xpath = new DOMXPath($dom);

        $title = '';
        $nodes = $xpath->query('//title');
        if ($nodes->length > 0) {
            $title = trim($nodes->item(0)->textContent);
        }

        $description = '';
        $nodes = $xpath->query('//meta[@name="description"]/@content');
        if ($nodes->length > 0) {
            $description = trim($nodes->item(0)->nodeValue);
        }

        $date  = '';
        $nodes = $xpath->query('//meta[@property="article:published_time"]/@content');
        if ($nodes->length > 0) {
            $date = trim($nodes->item(0)->nodeValue);
        }
        if ($date === '') {
            $nodes = $xpath->query('//time[@datetime]/@datetime');
            if ($nodes->length > 0) {
                $date = trim($nodes->item(0)->nodeValue);
            }
        }

        // Prefer <link rel="canonical"> over the request URL so the md=1
        // query param does not leak into the recorded canonical address.
        $url   = '';
        $nodes = $xpath->query('//link[@rel="canonical"]/@href');
        if ($nodes->length > 0) {
            $url = trim($nodes->item(0)->nodeValue);
        }
        if ($url === '') {
            $uri = clone Uri::getInstance();
            $uri->delVar('md');
            $url = $uri->toString();
        }

        return array_filter([
            'title'       => $title,
            'url'         => $url,
            'description' => $description,
            'date'        => $date,
        ]);
    }

    private function extractContent(string $html): string
    {
        $dom   = $this->parseDom($html);
        $xpath = new DOMXPath($dom);

        // Ordered preference: semantic → Joomla Cassiopeia → common templates.
        $selectors = [
            '//main',
            '//article',
            '//*[@role="main"]',
            '//*[contains(@class,"item-page")]',
            '//*[contains(@class,"com-content-article__body")]',
            '//*[@id="sp-component"]',
            '//*[contains(@class,"blog-item")]',
            '//body',
        ];

        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                return $dom->saveHTML($nodes->item(0));
            }
        }

        return $html;
    }

    private function toMarkdown(string $html): string
    {
        $converter = new HtmlConverter([
            'strip_tags'            => false,
            'remove_nodes'          => 'script style nav footer header aside',
            'hard_break'            => true,
            'preserve_comments'     => false,
            'header_style'          => 'atx',
        ]);

        return trim($converter->convert($html));
    }

    private function buildFrontmatter(array $meta): string
    {
        $lines = ['---'];
        foreach ($meta as $key => $value) {
            $lines[] = $key . ': ' . $this->yamlScalar($value);
        }
        $lines[] = '---';

        return implode("\n", $lines);
    }

    private function yamlScalar(string $value): string
    {
        // Quote if the value contains characters that would confuse a YAML parser.
        if (preg_match('/[:#\[\]{},|>&*!\'"%@`]/', $value) || str_starts_with($value, ' ')) {
            return '"' . str_replace('"', '\\"', $value) . '"';
        }

        return $value;
    }

    private function parseDom(string $html): DOMDocument
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        return $dom;
    }
}
