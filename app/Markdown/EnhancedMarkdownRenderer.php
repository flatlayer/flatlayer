<?php

namespace App\Markdown;

use Illuminate\Database\Eloquent\Model;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Output\RenderedContentInterface;

/**
 * Class EnhancedMarkdownRenderer
 *
 * This class provides enhanced Markdown rendering capabilities,
 * including custom image rendering for Eloquent models.
 */
class EnhancedMarkdownRenderer
{
    protected Environment $environment;

    protected MarkdownConverter $converter;

    /**
     * @param  Model  $model  The Eloquent model associated with the renderer
     * @param  Environment|null  $environment  Optional custom environment
     */
    public function __construct(protected Model $model, ?Environment $environment = null)
    {
        $this->environment = $environment ?? $this->createDefaultEnvironment();
        $this->environment->addRenderer(Image::class, new CustomImageRenderer($this->model, $this->environment));
        $this->converter = new MarkdownConverter($this->environment);
    }

    /**
     * Create a default CommonMark environment.
     */
    protected function createDefaultEnvironment(): Environment
    {
        $environment = new Environment([
            'allow_unsafe_links' => false,
            'max_nesting_level' => 100,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);

        return $environment;
    }

    /**
     * Convert Markdown to HTML.
     *
     * @param  string  $markdown  The Markdown content to convert
     * @return RenderedContentInterface The rendered HTML content
     */
    public function convertToHtml(string $markdown): RenderedContentInterface
    {
        return $this->converter->convert($markdown);
    }
}
