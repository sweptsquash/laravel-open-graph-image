<?php

namespace Backstage\OgImage\Laravel;

use Backstage\OgImage\Laravel\Http\Controllers\OgImageController;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Page;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\View\ComponentAttributeBag;

class OgImage
{
    public function routes(): void
    {
        if (app()->environment('local')) {
            Route::get('og-image/preview', [OgImageController::class, '__invoke'])->name('og-image.html');
        }

        Route::get('og-image', [OgImageController::class, '__invoke'])->name('og-image.file');
    }

    public function imageExtension(): string
    {
        return config('og-image.extension');
    }

    public function imageWidth(): int
    {
        return config('og-image.width');
    }

    public function imageHeight(): int
    {
        return config('og-image.height');
    }

    public function storageDisk(): string
    {
        return config('og-image.storage.disk');
    }

    public function storagePath($folder = null): string
    {
        return rtrim(config('og-image.storage.path')).($folder ? '/'.$folder : '');
    }

    public function getImageMimeType(): string
    {
        return 'image/'.config('og-image.extension');
    }

    public function getStorageDisk(): FilesystemAdapter
    {
        return Storage::disk($this->storageDisk());
    }

    public function getStoragePath(?string $folder = null): string
    {
        return rtrim($this->storagePath($folder), '/');
    }

    public function getStorageImageFileName(string $signature): string
    {
        return $signature.'.'.$this->imageExtension();
    }

    public function getStorageImageFilePath(string $signature): string
    {
        return $this->getStoragePath('images').'/'.$this->getStorageImageFileName($signature);
    }

    public function getStorageImageFileExists(string $signature): bool
    {
        return $this->getStorageDisk()
            ->exists($this->getStorageImageFilePath($signature));
    }

    public function getStorageImageFileData(string $signature): string
    {
        return $this->getStorageDisk()
            ->get($this->getStorageImageFilePath($signature));
    }

    public function getStorageViewFileName(string $signature): string
    {
        return $signature.'.blade.php';
    }

    public function getStorageViewFilePath(string $signature, ?string $folder = null): string
    {
        return $this->getStoragePath('views').'/'.$this->getStorageViewFileName($signature);
    }

    public function getStorageViewFileData(string $signature): string
    {
        return $this->getStorageDisk()
            ->get($this->getStorageViewFilePath($signature));
    }

    public function getStorageViewFileExists(string $signature): bool
    {
        return $this->getStorageDisk()
            ->exists($this->getStorageViewFilePath($signature));
    }

    public function ensureDirectoryExists(string $folder = ''): void
    {
        if (! File::isDirectory($this->getStoragePath($folder))) {
            File::makeDirectory($this->getStoragePath($folder), 0777, true);
        }
    }

    public function transformAttributeBagToArray(ComponentAttributeBag $attributes): array
    {
        return collect($attributes)->all();
    }

    public function url(array|ComponentAttributeBag $parameters): string
    {
        if ($parameters instanceof ComponentAttributeBag) {
            $parameters = $this->transformAttributeBagToArray($parameters);
        }

        $parameters = collect($parameters)
            ->merge(['.'.config('og-image.extension')]) // add image extension to url for twitter compatibility
            ->all();

        return url()
            ->signedRoute('og-image.file', $parameters);
    }

    public function getSignature(array|ComponentAttributeBag $parameters): string
    {
        if ($parameters instanceof ComponentAttributeBag) {
            $parameters = $this->transformAttributeBagToArray($parameters);
        }

        $url = $this->url($parameters);

        $url = parse_url($url);

        parse_str($url['query'], $parameters);

        return $parameters['signature'];
    }

    public function createImageFromParams(array $parameters): ?string
    {
        $signature = $this->getSignature($parameters);

        if (! OgImage::getStorageImageFileExists($signature)) {
            $html = View::make('og-image::template', $parameters)
                ->render();

            OgImage::saveImage($html, $signature);
        }

        return Storage::disk(config('og-image.storage.disk'))
            ->url(OgImage::getStorageImageFilePath($signature));
    }

    public function saveImage(string $html, string $filename): ?string
    {
        if (OgImage::getStorageImageFileExists($filename)) {
            return null;
        }

        OgImage::ensureDirectoryExists('images');

        return $this->takeScreenshot($html, $filename);
    }

    public function takeScreenshot(string $html, string $filename): string
    {
        $binary = (string) config('og-image.chrome.binary');

        $browserFactory = new BrowserFactory($binary);

        $browser = $browserFactory->createBrowser([
            'customFlags' => config('og-image.chrome.flags'),
        ]);

        $page = $browser->createPage();

        $page->setHtml(html: $html, timeout: 3000, eventName: Page::LOAD);
        $page->setViewport(config('og-image.width'), config('og-image.height'));

        $screenshot = $page->screenshot();

        $screenshot->saveToFile(
            path: $path = storage_path('app/public/'.OgImage::getStorageImageFilePath($filename)),
        );

        $browser->close();

        return $path;
    }

    public function getResponse(Request $request): Response
    {
        $this->generateImage($request);

        return response(OgImage::getStorageImageFileData($request->signature), 200, [
            'Content-Type' => OgImage::getImageMimeType(),
        ]);
    }

    public function generateImage($request)
    {
        if ($request->view && view()->exists($request->view)) {
            $html = View::make($request->view, $request->all())
                ->render();
        } elseif (OgImage::getStorageViewFileExists($request->signature)) {
            $html = OgImage::getStorageViewFileData($request->signature);
        } else {
            $html = View::make('og-image::template', $request->all())
                ->render();
        }

        if ($request->route()->getName() == 'og-image') {
            return $html;
        }

        OgImage::saveImage($html, $request->signature);
    }
}
