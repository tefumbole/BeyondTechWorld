<?php

namespace App\Http\Controllers;

use App\LetterCategory;
use App\LetterTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

class LetterTemplateController extends Controller
{
    public function __construct() {

        $this->middleware(function ($request, $next) {
            $role = Role::find(Auth::user()->role_id);
            $permissions = Role::findByName($role->name)->permissions;

            foreach ($permissions as $permission) {
                $all_permission[] = $permission->name;
            }
            View::share ( 'all_permission', $all_permission);

            return $next($request);
        });
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        $data = LetterTemplate::with('category')->where('is_active', true)->get();
        return view('letter_template.index', compact('data'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\LetterTemplate  $letterTemplate
     * @return \Illuminate\Http\Response
     */
    public function show(LetterTemplate $letterTemplate, $id)
    {
        $data = LetterTemplate::with('category', 'createdBy')->where('id', $id)->first();
        return view('letter_template.show', compact('data'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\LetterTemplate  $letterTemplate
     * @return \Illuminate\Http\Response
     */
    public function edit(LetterTemplate $letterTemplate, $id)
    {
        $category = LetterCategory::where('is_active', true)->get();
        $data = $letterTemplate->findorfail($id);

        return view('letter_template.edit', compact('category', 'data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\LetterTemplate  $letterTemplate
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, LetterTemplate $letterTemplate, $id)
    {
        $data = $request->all();
        $letterTemplate->find($id)->update($data);

        return redirect()->route('letter.template.index')->with('success', 'Letter template updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\LetterTemplate  $letterTemplate
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $data = LetterTemplate::find($id);
        $data->is_active = false;
        $data->save();
        return back()->with('not_permitted','Data deleted successfully');
    }
}
