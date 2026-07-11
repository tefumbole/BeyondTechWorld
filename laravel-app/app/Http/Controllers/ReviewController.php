<?php

namespace App\Http\Controllers;

use App\Product;
use App\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index()
    {
        //
    }

    public function store(Request $request)
    {
        $data = $request->all();
        $this->updateRating($request->rating, $request->product_id);
        Review::create($data);

        return back()->with('message', 'Your valuable review is submitted...!');
    }

    public function show(Review $review)
    {
        //
    }

    public function update(Request $request, Review $review)
    {
        $data = $request->all();
        $this->updateRating($request->rating, $request->product_id, $review->id);
        $review->update($data);

        return back()->with('message', 'Your review has been updated...!');
    }


    public function updateRating($rating, $product_id, $id = null)
    {
        $rating_count = Review::where('product_id', $product_id)->count('id');
        $rating_sum = Review::where('product_id', $product_id);
        if($id != null) {
            $rating_sum = $rating_sum->where('id', '!=' ,$id);
        } else {
            $rating_count = $rating_count + 1;
        }
        $rating_sum = $rating_sum->sum('rating');
        $rating_sum = $rating_sum + $rating;
        $rating = $rating_sum/$rating_count;

        Product::where('id', $product_id)->update(['rating' => $rating]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Review  $review
     * @return \Illuminate\Http\Response
     */
    public function destroy(Review $review)
    {
        //
    }
}
