<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.markdownmirror
 *
 * Minimal proof-of-concept plugin for serving Markdown output when md=1 is present.
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;

class PlgSystemMarkdownmirror extends CMSPlugin
{
    public function onAfterRender()
    {
        $app = Factory::getApplication();

        if (!$app->isClient('site')) {
            return;
        }

        $input = $app->input;

        if ((int) $input->getInt('md', 0) !== 1) {
            return;
        }

        $uri = Uri::getInstance();
        $path = $uri->getPath();

        // Remove .md from the visible URL path to get the canonical HTML URL.
        $canonicalPath = preg_replace('/\.md$/', '', $path);
        $canonicalUrl = 'https://buchs.dk' . $canonicalPath;

        $markdown = "# Markdown mode works\n\n";
        $markdown .= "Canonical URL: " . $canonicalUrl . "\n\n";
        $markdown .= "This is a test Markdown response from Joomla.\n\n";
        $markdown .= "If you can see this at the .md URL, the rewrite and plugin are working.\n";

        $app->setBody($markdown);

        if (!headers_sent()) {
            header('Content-Type: text/markdown; charset=utf-8');
            header('X-Robots-Tag: noindex, follow');
        }
    }
}
