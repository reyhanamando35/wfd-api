<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Utils\HttpResponse;
use App\Utils\HttpResponseCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

abstract class Controller
{
    use HttpResponse;
    protected $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //Log::info($this->model->with($this->model->relations())->get());
        return $this->success('Successfully retrieved data', $this->model->with($this->model->relations())->get());
    }

    /**
     * Store a newly created resource in storage.
     */

    public function store(Request $request)
    {
        $data = $request->only($this->model->getFillable());

        $valid = Validator::make($data, $this->model->validationRules(), $this->model->validationMessages());

        if ($valid->fails()) {
            $validationError = $valid->errors()->first();
            return $this->error($validationError, HttpResponseCode::HTTP_NOT_ACCEPTABLE);
        }

        $model = $this->model->create($data);
        return $this->success('Data successfully saved to model', $model);
    }
    /**
     * Display the specified resource by ID.
     */
    public function show(string $id)
    {
        return $this->success('Successfully retrieved data', $this->model->with($this->model->relations())->findOrFail($id));
    }

    function showWithoutRelationship(string $id)
    {
        return $this->success('Successfully retrieved data', $this->model->find($id));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $data = $request->only($this->model->getFillable());

        $valid = Validator::make($data, $this->model->validationRules(), $this->model->validationMessages());

        if ($valid->fails()) {
            $validationError = $valid->errors()->first();
            return $this->error($validationError, HttpResponseCode::HTTP_NOT_ACCEPTABLE);
        }

        $update = $this->model->find($id);
        if (!$update) {
            return $this->error('ID not found !');
        }

        $update->update($data);

        return $this->success('Successfully updated a data', $update);
    }

    /**
     * Update the specified resource in storage partially.
     */
    public function updatePartial(Request $request, $id)
    {
        $fillableKey = [];

        foreach ($this->model->getFillable() as $field) {
            if ($request->has($field)) {
                $fillableKey[] = $field;
            }
        }

        $requestFillable = $request->only($fillableKey);

        if (empty($requestFillable)) {
            return $this->error('There is no correct field to update', HttpResponseCode::HTTP_NOT_ACCEPTABLE);
        }

        $validationRules = [];
        $modelValidationRules = $this->model->validationRules();

        foreach ($fillableKey as $field) {
            if (isset($modelValidationRules[$field])) {
                $validationRules[$field] = $modelValidationRules[$field];
            }
        }

        $validator = Validator::make(
            $requestFillable,
            $validationRules,
            $this->model->validationMessages()
        );

        if ($validator->fails()) {
            $validationError = $validator->errors()->first();
            return $this->error($validationError, HttpResponseCode::HTTP_NOT_ACCEPTABLE);
        }

        $update = $this->model->find($id);
        if (!$update) {
            return $this->error('Data not found', HttpResponseCode::HTTP_NOT_FOUND);
        }

        $update->update($requestFillable);

        return $this->success('Successfully updated the data', $update);
    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $data = $this->model->find($id);

        if (!$data) {
            return $this->error('ID not found!');
        }
        $data->delete();

        return $this->success('Successfully deleted a data!');
    }
}
