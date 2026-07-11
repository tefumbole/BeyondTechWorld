<?php

namespace App\Http\Controllers;

use App\LetterCategory;
use Illuminate\Http\Request;

class LetterCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $lims_letter_category_all = LetterCategory::where('is_active', true)->get();
        return view('letter_category.index', compact('lims_letter_category_all'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = $request->all();
        LetterCategory::create($data);
        return back()->with('message', 'Data inserted successfully');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\LetterCategory  $letterCategory
     * @return \Illuminate\Http\Response
     */
    public function show(LetterCategory $letterCategory)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\LetterCategory  $letterCategory
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $lims_expense_category_data = LetterCategory::find($id);
        return $lims_expense_category_data;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\LetterCategory  $letterCategory
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $data = $request->all();
        $lims_expense_category_data = LetterCategory::find($data['letter_category_id']);
        unset($data['letter_category_id']);
        $lims_expense_category_data->update($data);
        return back()->with('message', 'Data updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\LetterCategory  $letterCategory
     * @return \Illuminate\Http\Response
     */
    public function destroy(LetterCategory $letterCategory, $id)
    {
        $data = LetterCategory::find($id);
        $data->is_active = false;
        $data->save();
        return back()->with('not_permitted','Data deleted successfully');
    }
}
