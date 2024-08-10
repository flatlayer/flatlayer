<?php

namespace App\Markdown;

use App\Models\Image;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image as ImageNode;
use League\CommonMark\Extension\CommonMark\Renderer\Inline\ImageRenderer;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\Config\ConfigurationAwareInterface;
use League\Config\ConfigurationInterface;

/**
 * Custom renderer for image nodes in Markdown.
 * Adds in srcset, sizes and general responsive image handling.
 */
class CustomImageRenderer implements ConfigurationAwareInterface, NodeRendererInterface
{
    private ImageRenderer $defaultRenderer;

    private ConfigurationInterface $config;

    /**
     * @param  Model  $model  The model associated with the renderer
     * @param  Environment  $environment  The CommonMark environment
     */
    public function __construct(protected Model $model, Environment $environment)
    {
        $this->defaultRenderer = new ImageRenderer;
        $this->config = $environment->getConfiguration();

        if ($this->defaultRenderer instanceof ConfigurationAwareInterface) {
            $this->defaultRenderer->setConfiguration($this->config);
        }
    }

    /**
     * Render an image node.
     *
     * @param  Node  $node  The node to render
     * @param  ChildNodeRendererInterface  $childRenderer  The child node renderer
     * @return HtmlElement|string The rendered output
     *
     * @throws \InvalidArgumentException If the node type is incompatible
     */
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): HtmlElement|string
    {
        if (! ($node instanceof ImageNode)) {
            throw new \InvalidArgumentException('Incompatible node type: '.get_class($node));
        }

        $url = $node->getUrl();

        if (Str::startsWith($url, '/') || Str::startsWith($url, '\\')) {
            // This is an absolute file path
            $image = $this->model->images()->where('path', $url)->first();

            // Get the alt text from the first child node if it exists
            $alt = $this->getNodeAlt($node);

            if ($image instanceof Image) {
                return new HtmlElement('div', ['class' => 'markdown-image'], [
                    $image->getImgTag(['100vw'], ['alt' => $alt ?? basename($url)]),
                ]);
            }
        }

        // If not a local file or media not found, fall back to default rendering
        return $this->defaultRenderer->render($node, $childRenderer);
    }

    /**
     * Get the alt text from the node.
     *
     * @param  Node  $node  The node to extract alt text from
     * @return string|null The alt text, or null if not found
     */
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

    /**
     * Set the configuration for the renderer.
     *
     * @param  ConfigurationInterface  $configuration  The configuration to set
     */
    public function setConfiguration(ConfigurationInterface $configuration): void
    {
        $this->config = $configuration;
        if ($this->defaultRenderer instanceof ConfigurationAwareInterface) {
            $this->defaultRenderer->setConfiguration($configuration);
        }
    }
}
