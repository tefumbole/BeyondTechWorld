<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\ExpenseCategory;
use Illuminate\Validation\Rule;

class ExpenseCategoryController extends Controller
{
    public function index()
    {
        $lims_expense_category_all = ExpenseCategory::where('is_active', true)->get();
        return view('expense_category.index', compact('lims_expense_category_all'));
    }

    public function create()
    {
        //
    }

    public function generateCode()
    {
        return $this->nextSequentialCode();
    }

    protected function nextSequentialCode()
    {
        $codes = ExpenseCategory::pluck('code');
        $max = 0;
        foreach ($codes as $code) {
            $code = trim((string) $code);
            if (! preg_match('/^\d{1,4}$/', $code)) {
                continue;
            }
            $n = (int) $code;
            if ($n > $max) {
                $max = $n;
            }
        }

        return str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    public function store(Request $request)
    {
        $code = trim((string) $request->get('code'));
        if ($code === '') {
            $code = $this->nextSequentialCode();
            $request->merge(['code' => $code]);
        }

        $this->validate($request, [
            'code' => [
                'required',
                'max:255',
                Rule::unique('expense_categories')->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ],
            'name' => 'required|string|max:255',
        ]);

        $data = $request->all();
        if (empty($data['is_active'])) {
            $data['is_active'] = 1;
        }
        ExpenseCategory::create($data);
        return redirect('expense_categories')->with('message', 'Data inserted successfully');
    }

    public function show($id)
    {
        //
    }

    public function edit($id)
    {
        $lims_expense_category_data = ExpenseCategory::find($id);
        return $lims_expense_category_data;
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'code' => [
                'max:255',
                    Rule::unique('expense_categories')->ignore($request->expense_category_id)->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ]
        ]);

        $data = $request->all();
        $lims_expense_category_data = ExpenseCategory::find($data['expense_category_id']);
        $lims_expense_category_data->update($data);
        return redirect('expense_categories')->with('message', 'Data updated successfully');
    }

    public function import(Request $request)
    {
        //get file
        $upload=$request->file('file');
        $ext = pathinfo($upload->getClientOriginalName(), PATHINFO_EXTENSION);
        if($ext != 'csv')
            return redirect()->back()->with('not_permitted', 'Please upload a CSV file');
        $filename =  $upload->getClientOriginalName();
        $filePath=$upload->getRealPath();
        //open and read
        $file=fopen($filePath, 'r');
        $header= fgetcsv($file);
        $escapedHeader=[];
        //validate
        foreach ($header as $key => $value) {
            $lheader=strtolower($value);
            $escapedItem=preg_replace('/[^a-z]/', '', $lheader);
            array_push($escapedHeader, $escapedItem);
        }
        //looping through othe columns
        while($columns=fgetcsv($file))
        {
            if($columns[0]=="")
                continue;
            foreach ($columns as $key => $value) {
                $value=preg_replace('/\D/','',$value);
            }
           $data= array_combine($escapedHeader, $columns);
           $expense_category = ExpenseCategory::firstOrNew(['code' => $data['code'], 'is_active' => true ]);
           $expense_category->code = $data['code'];
           $expense_category->name = $data['name'];
           $expense_category->is_active = true;
           $expense_category->save();
        }
        return redirect('expense_categories')->with('message', 'ExpenseCategory imported successfully');
    }

    public function deleteBySelection(Request $request)
    {
        $expense_category_id = $request['expense_categoryIdArray'];
        foreach ($expense_category_id as $id) {
            $lims_expense_category_data = ExpenseCategory::find($id);
            $lims_expense_category_data->is_active = false;
            $lims_expense_category_data->save();
        }
        return 'Expense Category deleted successfully!';
    }

    public function destroy($id)
    {
        $lims_expense_category_data = ExpenseCategory::find($id);
        $lims_expense_category_data->is_active = false;
        $lims_expense_category_data->save();
        return redirect('expense_categories')->with('not_permitted', 'Data deleted successfully');
    }
}
