<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Cms\CmsSearchDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;

class CmsMarkdownController extends Controller
{
    public function llms(): Response
    {
        $documents = CmsSearchDocument::query()
            ->where('is_active', true)
            ->where('is_searchable', true)
            ->where('noindex', false)
            ->orderBy('locale')
            ->orderBy('source_type')
            ->orderBy('title')
            ->get(['locale', 'source_type', 'title', 'markdown_url', 'summary']);

        $lines = ['# '.config('app.name'), ''];

        foreach ($documents->groupBy('locale') as $locale => $localeDocuments) {
            $lines[] = '## '.strtoupper((string) $locale);
            $lines[] = '';

            foreach ($localeDocuments->groupBy('source_type') as $sourceType => $groupDocuments) {
                $lines[] = '### '.str_replace('_', ' ', ucfirst((string) $sourceType));
                $lines[] = '';

                foreach ($groupDocuments as $document) {
                    $summary = trim((string) $document->summary);
                    $lines[] = '- ['.$document->title.']('.$document->markdown_url.')'.($summary !== '' ? ' - '.$summary : '');
                }

                $lines[] = '';
            }
        }

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Robots-Tag' => 'noindex, follow',
        ]);
    }

    public function index(Request $request, string $locale): Response
    {
        App::setLocale($locale);

        $documents = CmsSearchDocument::query()
            ->where('locale', $locale)
            ->where('is_active', true)
            ->where('is_searchable', true)
            ->where('noindex', false)
            ->orderBy('source_type')
            ->orderBy('title')
            ->get(['source_type', 'title', 'markdown_url', 'summary']);
        $lines = ['# Markdown', ''];

        foreach ($documents->groupBy('source_type') as $sourceType => $groupDocuments) {
            $lines[] = '## '.str_replace('_', ' ', ucfirst((string) $sourceType));
            $lines[] = '';

            foreach ($groupDocuments as $document) {
                $summary = trim((string) $document->summary);
                $lines[] = '- ['.$document->title.']('.$document->markdown_url.')'.($summary !== '' ? ' - '.$summary : '');
            }

            $lines[] = '';
        }

        return $this->respond($request, implode("\n", $lines)."\n", 'Markdown', 'markdown-index.md', url($request->path()), now());
    }

    public function page(Request $request, string $locale, string $path): Response
    {
        return $this->documentResponse($request, $locale, 'pages/'.trim($path, '/'));
    }

    public function blogIndex(Request $request, string $locale): Response
    {
        return $this->documentResponse($request, $locale, 'blogs');
    }

    public function blogPost(Request $request, string $locale, string $slug): Response
    {
        return $this->documentResponse($request, $locale, 'blogs/'.$slug);
    }

    public function categoryIndex(Request $request, string $locale): Response
    {
        return $this->documentResponse($request, $locale, 'blogs/categories');
    }

    public function category(Request $request, string $locale, string $path): Response
    {
        return $this->documentResponse($request, $locale, 'blogs/categories/'.trim($path, '/'));
    }

    public function tagIndex(Request $request, string $locale): Response
    {
        return $this->documentResponse($request, $locale, 'blogs/tags');
    }

    public function tag(Request $request, string $locale, string $slug): Response
    {
        return $this->documentResponse($request, $locale, 'blogs/tags/'.$slug);
    }

    public function docsIndex(Request $request, string $locale): Response
    {
        return $this->documentResponse($request, $locale, 'docs');
    }

    public function docsCollection(Request $request, string $locale, string $collection): Response
    {
        return $this->documentResponse($request, $locale, 'docs/'.$collection);
    }

    public function docsVersion(Request $request, string $locale, string $collection, string $version): Response
    {
        return $this->documentResponse($request, $locale, 'docs/'.$collection.'/'.$version);
    }

    public function docsPage(Request $request, string $locale, string $collection, string $version, string $path): Response
    {
        return $this->documentResponse($request, $locale, 'docs/'.$collection.'/'.$version.'/'.trim($path, '/'));
    }

    private function documentResponse(Request $request, string $locale, string $relativePath): Response
    {
        App::setLocale($locale);

        $document = CmsSearchDocument::query()
            ->where('locale', $locale)
            ->where('markdown_path', $this->rawMarkdownPath($locale, $relativePath))
            ->where('is_active', true)
            ->where('is_searchable', true)
            ->where('noindex', false)
            ->firstOrFail();

        return $this->respond(
            $request,
            (string) $document->markdown,
            (string) $document->title,
            $this->downloadFileName($document),
            (string) $document->canonical_url,
            $document->source_updated_at ?? $document->updated_at,
        );
    }

    private function respond(Request $request, string $markdown, string $title, string $downloadFileName, string $htmlUrl, mixed $updatedAt): Response
    {
        $mode = (string) $request->route('markdownMode', 'raw');
        $etag = '"'.sha1($markdown).'"';
        $lastModified = $updatedAt ? Carbon::parse($updatedAt)->toRfc7231String() : null;

        if ($mode === 'download') {
            return response()->streamDownload(function () use ($markdown): void {
                echo $markdown;
            }, $downloadFileName, [
                'Content-Type' => 'text/markdown; charset=UTF-8',
                'X-Robots-Tag' => 'noindex, follow',
                'Link' => '<'.$htmlUrl.'>; rel="canonical"',
            ]);
        }

        if ($mode === 'preview') {
            return response()->view('public.system.markdown.preview', [
                'title' => $title,
                'markdown' => $markdown,
                'rawUrl' => str_replace('/markdown-view/', '/markdown/', $request->fullUrl()),
                'downloadUrl' => str_replace('/markdown-view/', '/markdown-download/', $request->fullUrl()),
                'htmlUrl' => $htmlUrl,
            ], 200, [
                'X-Robots-Tag' => 'noindex, follow',
                'Link' => '<'.$htmlUrl.'>; rel="canonical"',
            ]);
        }

        if ((string) $request->header('If-None-Match') === $etag) {
            return response('', 304, $this->cacheHeaders($etag, $htmlUrl, $lastModified));
        }

        return response($markdown, 200, array_merge($this->cacheHeaders($etag, $htmlUrl, $lastModified), [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Cache-Control' => 'public, max-age=600',
        ]));
    }

    /**
     * @return array<string, string>
     */
    private function cacheHeaders(string $etag, string $htmlUrl, ?string $lastModified): array
    {
        return array_filter([
            'ETag' => $etag,
            'Last-Modified' => $lastModified,
            'X-Robots-Tag' => 'noindex, follow',
            'Link' => '<'.$htmlUrl.'>; rel="canonical"',
        ]);
    }

    private function rawMarkdownPath(string $locale, string $relativePath): string
    {
        return '/'.trim($locale, '/').'/markdown/'.trim($relativePath, '/');
    }

    private function downloadFileName(CmsSearchDocument $document): string
    {
        return str_replace(['/', ' '], ['-', '-'], trim($document->source_type.'-'.$document->slug, '-')).'.md';
    }
}
