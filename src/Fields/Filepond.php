<?php

declare(strict_types=1);

namespace MoonShine\Filepond\Fields;

use Closure;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use JsonException;
use MoonShine\AssetManager\Css;
use MoonShine\AssetManager\Js;
use MoonShine\Crud\Exceptions\FileFieldException;
use MoonShine\Support\DTOs\FileItem;
use MoonShine\UI\Components\Files;
use MoonShine\UI\Exceptions\FieldException;
use MoonShine\UI\Fields\Field;
use MoonShine\UI\Traits\Fields\CanBeMultiple;
use MoonShine\UI\Traits\Fields\FileDeletable;
use MoonShine\UI\Traits\Fields\FileTrait;
use MoonShine\UI\Traits\Removable;

class Filepond extends Field
{
    use CanBeMultiple;
    use FileTrait;
    use FileDeletable;
    use Removable;

    protected string $view = 'moonshine-filepond::default';

    protected ?string $panelAspectRatio = null;

    protected bool $isCompact = false;

    protected int $itemHeight = 100;

    protected int $itemMinHeight = 44;

    protected int $itemMaxHeight = 100;

    protected bool $gridLayout = true;

    protected function assets(): array
    {
        return [
            Js::make('vendor/moonshine-filepond/filepond.js'),
            Css::make('vendor/moonshine-filepond/filepond.css'),
        ];
    }

    public function itemHeight(int $height, ?int $min = null, ?int $max = null): static
    {
        $this->itemHeight = $height;
        $this->itemMinHeight = $min ?? $height;
        $this->itemMaxHeight = $max ?? $height;

        return $this;
    }

    /**
     * Set aspect ratio for panel (e.g. '1:1', '4:3', '16:9')
     */
    public function aspectRatio(string $ratio): static
    {
        $this->panelAspectRatio = $ratio;

        return $this;
    }

    /**
     * Enable compact layout (preview replaces drop area)
     */
    public function compact(): static
    {
        $this->isCompact = true;

        return $this;
    }

    /**
     * Disable grid layout (items will be stacked vertically)
     */
    public function vertical(): static
    {
        $this->gridLayout = false;

        return $this;
    }

    public function reorderable(string|Closure $url, ?string $group = null): static
    {
        throw new FieldException('Reorderable is enabled by default');
    }

    public function itemAttributes(Closure $callback): static
    {
        throw new FieldException('Not implemented');
    }

    public function extraAttributes(Closure $callback): static
    {
        throw new FieldException('Not implemented');
    }

    protected function resolveOnApply(): ?Closure
    {
        return function (mixed $item): mixed {
            $requestValue = $this->getRequestValue();
            $remainingValues = $this->getRemainingValues();

            data_forget($item, $this->getHiddenRemainingValuesKey());

            $newValue = $this->isMultiple() ? $remainingValues : $remainingValues->first();

            if ($requestValue !== false) {
                if ($this->isMultiple()) {
                    $paths = [];

                    foreach ($requestValue as $file) {
                        $paths[] = $file;
                    }

                    $newValue = $newValue->merge($paths)
                        ->values()
                        ->unique()
                        ->toArray();
                } else {
                    $newValue = $requestValue;
                    $this->setRemainingValues([]);
                }
            }

            if ($newValue instanceof Collection) {
                $newValue = $newValue->toArray();
            }

            $this->removeExcludedFiles(
                $this->getCustomName() !== null || $this->isKeepOriginalFileName()
                    ? $newValue
                    : null,
            );

            return data_set($item, $this->getColumn(), $newValue);
        };
    }

    protected function resolveRawValue(): mixed
    {
        $values = $this->getFullPathValues();

        return implode(';', array_filter($values));
    }

    protected function resolvePreview(): Renderable|string
    {
        return Files::make(
            $this->getFiles()->toArray(),
            download: $this->canDownload(),
        )->render();
    }

    /**
     * @return Collection<int, FileItem>
     */
    protected function getFiles(): Collection
    {
        /** @var Collection<array-key, string> $collection */
        $collection = new Collection($this->getFullPathValues());

        return $collection
            ->mapWithKeys(fn (string $path, int $index): array => [
                $index => new FileItem(
                    fullPath: $path,
                    rawValue: data_get($this->toValue(), $index, $this->toValue()) ?? $path,
                    name: \call_user_func($this->resolveNames(), $path, $index, $this),
                    attributes: \call_user_func($this->resolveItemAttributes(), $path, $index, $this),
                    extra: \call_user_func($this->resolveExtraAttributes(), $path, $index, $this),
                ),
            ]);
    }

    public function getRequestValue(int|string|null $index = null): mixed
    {
        return $this->prepareRequestValue(
            $this->getCore()->getRequest()->getFile(
                $this->getRequestNameDot($index),
            ) ?? false
        );
    }

    /**
     * @throws JsonException
     */
    protected function prepareBeforeRender(): void
    {
        parent::prepareBeforeRender();

        $resource = $this->getNowOnResource() ?? $this->getCore()->getCrudRequest()->getResource();
        $page = $this->getNowOnPage() ?? $this->getCore()->getCrudRequest()->getPage();
        $item = $this->getData()?->getKey();

        $attributes = [
            'data-server' => $this
                ->getCore()->getRouter()
                ->to('filepond.upload', [
                    '_field' => $this->getColumn(),
                    'pageUri' => $page->getUriKey(),
                    'resourceUri' => $resource?->getUriKey(),
                    'resourceItem' => $item,
                ]),
            'data-extensions' => $this->getAcceptExtension(),
            'data-labels' => json_encode(__('moonshine-filepond::filepond'), JSON_THROW_ON_ERROR),
            'data-preview-height' => $this->itemHeight,
            'data-preview-min-height' => $this->itemMinHeight,
            'data-preview-max-height' => $this->itemMaxHeight,
            'data-poster-height' => $this->itemHeight,
            'data-panel-aspect-ratio' => $this->panelAspectRatio,
            'data-compact' => $this->isCompact ? 'true' : null,
            'data-allow-remove' => $this->isRemovable() ? 'true' : 'false',
            'data-allow-reorder' => 'true',
            'data-allow-revert' => 'false',
            'data-grid' => $this->gridLayout ? 'true' : null,
        ];

        $files = $this->getFilepondFormattedFiles();

        if ($files !== []) {
            $attributes['data-files'] = json_encode($files, JSON_THROW_ON_ERROR);
        }

        $this->customAttributes($attributes)->removeAttribute('accept');
    }

    public function store(UploadedFile $file): string
    {
        $extension = $file->extension();

        if (! $this->isAllowedExtension($extension)) {
            throw FileFieldException::extensionNotAllowed($extension);
        }

        if ($this->isKeepOriginalFileName()) {
            return $file->storeAs(
                $this->getDir(),
                $file->getClientOriginalName(),
                $this->getOptions(),
            );
        }

        if (! \is_null($this->getCustomName())) {
            return $file->storeAs(
                $this->getDir(),
                \call_user_func($this->getCustomName(), $file, $this),
                $this->getOptions(),
            );
        }

        if (! $result = $file->store($this->getDir(), $this->getOptions())) {
            throw FileFieldException::failedSave();
        }

        return $result;
    }

    protected function getFilepondFormattedFiles(): array
    {
        $value = $this->toValue();

        if (empty($value)) {
            return [];
        }

        $files = is_array($value) ? $value : [$value];
        $disk = $this->getDisk();

        return Collection::make($files)
            ->filter(static fn($file): bool => $file && Storage::disk($disk)->exists($file))
            ->map(fn(string $file, int $index): array => [
                'source' => $file,
                'options' => [
                    'type' => 'local',
                    'file' => [
                        'name' => \call_user_func($this->resolveNames(), basename($file), $index, $this),
                        'size' => Storage::disk($disk)->size($file),
                    ],
                    'metadata' => [
                        'poster' => $this->getStorageUrl($file),
                    ],
                ],
            ])
            ->values()
            ->all();
    }

    protected function viewData(): array
    {
        return [
            'hiddenAttributes' => $this->getHiddenAttributes(),
            'dropzoneAttributes' => $this->getDropzoneAttributes(),
        ];
    }
}
