<?php

namespace App\Markdown;

use App\Models\MediaFile;
use Illuminate\Support\Str;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Renderer\Inline\ImageRenderer;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Node\Node;
use League\CommonMark\Util\HtmlElement;

class EnhancedMarkdownRenderer implements NodeRendererInterface
{
    protected $model;

    public function __construct($model)
    {
        $this->model = $model;
    }

    public function render(Node $node, $inlineContext = null)
    {
        if (!($node instanceof Image)) {
            throw new \InvalidArgumentException('Incompatible node type: ' . get_class($node));
        }

        $url = $node->getUrl();

        if (Str::startsWith($url, '/') || Str::startsWith($url, '\\')) {
            // This is an absolute file path
            $filename = basename($url);
            $media = $this->model->media()->where('filename', $filename)->first();

            if ($media instanceof MediaFile) {
                return new HtmlElement('div', ['class' => 'markdown-image'], [
                    $media->getImgTag('100vw', ['alt' => $node->getTitle() ?: $filename])
                ]);
            }
        }

        // If not a local file or media not found, fall back to default rendering
        $defaultRenderer = new ImageRenderer();
        return $defaultRenderer->render($node, $inlineContext);
    }
}
