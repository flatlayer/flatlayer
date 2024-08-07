<?php

namespace App\Markdown;

use App\Models\MediaFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Renderer\Inline\ImageRenderer;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\Config\ConfigurationAwareInterface;
use League\Config\ConfigurationInterface;
use League\Config\ReadOnlyConfiguration;

class EnhancedMarkdownRenderer implements NodeRendererInterface, ConfigurationAwareInterface
{
    private ImageRenderer $defaultRenderer;
    private ReadOnlyConfiguration $config;

    public function __construct(protected Model $model)
    {
        $this->defaultRenderer = new ImageRenderer();
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer = null)
    {
        if (!($node instanceof Image)) {
            throw new \InvalidArgumentException('Incompatible node type: ' . get_class($node));
        }

        $url = $node->getUrl();

        if (Str::startsWith($url, '/') || Str::startsWith($url, '\\')) {
            // This is an absolute file path
            $media = $this->model->media()->where('path', $url)->first();

            // Get the alt text from the first child node if it exists
            $alt = $this->getNodeAlt($node);

            if ($media instanceof MediaFile) {
                return new HtmlElement('div', ['class' => 'markdown-image'], [
                    $media->getImgTag(['100vw'], ['alt' => $alt ?? basename($url)]),
                ]);
            }
        }

        // If not a local file or media not found, fall back to default rendering
        return $this->defaultRenderer->render($node, $childRenderer);
    }

    protected function getNodeAlt(Node $node): ?string
    {
        // Get the alt text from the first child node if it exists
        foreach ($node->children() as $child) {
            if ($child instanceof Text) {
                return $child->getLiteral();
            }
        }

        return null;
    }

    public function setConfiguration(ConfigurationInterface $configuration): void
    {
        $this->config = $configuration;
        if ($this->defaultRenderer instanceof ConfigurationAwareInterface) {
            $this->defaultRenderer->setConfiguration($configuration);
        }
    }
}
