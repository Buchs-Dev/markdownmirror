<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.markdownmirror
 */

namespace Buchs\Plugin\System\Markdownmirror\Extension;

defined('_JEXEC') or die;

use DOMDocument;
use DOMXPath;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\SubscriberInterface;
use League\HTMLToMarkdown\HtmlConverter;

class Markdownmirror extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onAfterRender' => 'onAfterRender',
        ];
    }

    public function onAfterRender(): void
    {
        $app = Factory::getApplication();

        if (!$app->isClient('site')) {
            return;
        }

        $wantsMarkdown = (int) $app->input->getInt('md', 0) === 1
            || $this->acceptsMarkdown($_SERVER['HTTP_ACCEPT'] ?? '')
            || $this->isAiAgent($_SERVER['HTTP_USER_AGENT'] ?? '');

        if (!$wantsMarkdown) {
            return;
        }

        $html = $app->getBody();

        $meta        = $this->extractMeta($html);
        $contentHtml = $this->extractContent($html);
        $markdown    = $this->toMarkdown($contentHtml);
        $output      = $this->buildFrontmatter($meta) . "\n\n" . $markdown;

        $app->setBody($output);

        $app->setHeader('Content-Type', 'text/markdown; charset=utf-8', true);
        $app->setHeader('X-Robots-Tag', 'noindex, follow', true);
        $app->setHeader('Vary', 'Accept', false);
        $app->setHeader('X-Markdown-Tokens', (string) $this->estimateTokens($output), true);
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

        $nodes = $xpath->query('//article');
        if ($nodes->length > 0) {
            return $dom->saveHTML($nodes->item(0));
        }

        return '';
    }

    private function toMarkdown(string $html): string
    {
        $converter = new HtmlConverter([
            'strip_tags'            => true,
            'remove_nodes'          => 'script style nav footer header aside',
            'hard_break'            => true,
            'preserve_comments'     => false,
            'header_style'          => 'atx',
        ]);

        $markdown = $converter->convert($html);
        $markdown = preg_replace("/\n{3,}/", "\n\n", $markdown);

        return trim($markdown);
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

    private function acceptsMarkdown(string $accept): bool
    {
        return stripos($accept, 'text/markdown') !== false;
    }

    private function isAiAgent(string $userAgent): bool
    {
        if ($userAgent === '') {
            return false;
        }

        $patterns = [
            'GPTBot', 'ChatGPT-User', 'OAI-SearchBot',
            'ClaudeBot', 'Claude-Web', 'anthropic-ai',
            'Google-Extended', 'Googlebot-Extended',
            'PerplexityBot', 'YouBot', 'CCBot',
            'Amazonbot', 'cohere-ai', 'AI2Bot',
            'Applebot-Extended', 'Diffbot', 'FacebookBot',
            'Timpibot', 'PetalBot',
        ];

        foreach ($patterns as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    private function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
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