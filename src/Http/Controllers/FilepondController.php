<?php

declare(strict_types=1);

namespace MoonShine\Filepond\Http\Controllers;

use MoonShine\Filepond\Fields\Filepond;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use MoonShine\Contracts\Core\CrudPageContract;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;

class FilepondController extends Controller
{
    private function getField(CrudRequestContract $request): Filepond
    {
        $page = $request->getPage();

        if(!$page instanceof CrudPageContract) {
            abort(Response::HTTP_BAD_REQUEST, 'Crud page field not found');
        }

        /** @var ?Filepond $field */
        $field = $page->getFields()->onlyFields()->findByColumn($request->input('_field'));

        if($field === null) {
            abort(Response::HTTP_BAD_REQUEST, 'Filepond field not found');
        }

        return $field;
    }

    public function upload(CrudRequestContract $request): JsonResponse
    {
        $field = $this->getField($request);
        $file = $request->file('filepond');

        if (!$file) {
            abort(Response::HTTP_BAD_REQUEST, 'No file uploaded');
        }

        $path = $field->store($file);

        return response()->json([
            'path' => $path,
            'url' => $field->getStorageUrl($path),
        ], Response::HTTP_CREATED);
    }
}
