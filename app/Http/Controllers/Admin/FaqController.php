<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController as BaseController;
use Illuminate\Http\Request;
use App\Models\Faq;
use Validator;

class FaqController extends BaseController
{
    // addFaq
    public function addFaq(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'question' => 'required|array|unique:faqs,question',
            'answer' => 'required|array',
            'question.*' => 'required|string|distinct|max:255', // Prevent duplicates in request
            'answer.*'   => 'required|string|max:255',
        ], [
            'question.*.distinct' => 'Duplicate question detected in the list.',
            'question.*.required' => 'Each question is required.',
            'answer.*.required'   => 'Each answer is required.',
            'question.*.max'      => 'Each question must not be longer than 255 characters.',
             'answer.*.max'        => 'Each answer must not be longer than 255 characters.',
        ]);


        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'status' => 400,
                'error' => true,
            ], 422);
        }

        $faqs = [];

        foreach ($request->question as $key => $question) {
            if (!isset($request->answer[$key])) {
                return response()->json([
                    'message' => "Answer is missing for question: $question",
                    'status' => 400,
                    'error' => true,
                ], 422);
            }

            $faq = new Faq();
            $faq->question = $question;
            $faq->answer = $request->answer[$key];
            $faq->save();

            $faqs[] = $faq; // Store created FAQ for response
        }

        return response()->json([
            'data' => $faqs,
            'message' => 'Faq Added Successfully',
            'status' => 200,
            'error' => false,
        ], 200);
    }

    // deleteFaq
    public function deleteFaq($id)
    {
        $faq = Faq::find($id);

        if (!$faq) {
            return response()->json([
                'message' => 'FAQ Id not found',
                'status' => 404,
                'error' => true,
            ], 404);
        }

        $faq->delete();

        return response()->json([
            'message' => 'FAQ deleted successfully',
            'status' => 200,
            'error' => false,
        ]);
    }

    //UpdateFaq
    public function updateFaq(Request $request, $id)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|unique:faqs,question,' . $request->id,
            'answer' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'status' => 400,
                'error' => true,
            ], 422);
        }

        // Find the issue option by ID
        $faq = Faq::find($id);

        if (!$faq) {
            return response()->json([
                'data' => [],
                'message' => 'Faq Id Not Found',
                'status' => 404,
                'error' => true,
            ], 404);
        }


        // Update fields

        $faq->question = $request->question ?? $faq->question;
        $faq->answer = $request->answer ?? $faq->answer;
        $faq->save();

        return response()->json([
            'data' => $faq,
            'message' => 'FAQ  Updated Successfully',
            'status' => 200,
            'error' => false,
        ], 200);
    }

    //UpdateorAddFaq
    public function updateOrAddFaq(Request $request)
    {
        // Validate that "faqs" is provided as an array with at least one item.
        $validator = Validator::make($request->all(), [
            'faqs' => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'status'  => 400,
                'error'   => true,
            ], 422);
        }

        $updatedFaqs = [];

        foreach ($request->faqs as $faqData) {

            // If a "question" is provided, check if it already exists in the database.
            // For updates, ignore the record with the current ID.
            if (isset($faqData['question'])) {
                $query = Faq::where('question', $faqData['question']);
                if (isset($faqData['id'])) {
                    $query->where('id', '!=', $faqData['id']);
                }
                if ($query->exists()) {
                    return response()->json([
                        'message' => 'The question has already exists.',
                        'status'  => 422,
                        'error'   => true,
                    ], 422);
                }
            }

            if (isset($faqData['id'])) {
                // Update existing FAQ.
                $faq = Faq::find($faqData['id']);
                if (!$faq) {
                    return response()->json([
                        'message' => 'Faq Id Not Found',
                        'status'  => 404,
                        'error'   => true,
                    ], 404);
                }
                // Update only the provided fields.
                if (array_key_exists('question', $faqData)) {
                    $faq->question = $faqData['question'];
                }
                if (array_key_exists('answer', $faqData)) {
                    $faq->answer = $faqData['answer'];
                }
            } else {
                // For new FAQs, both "question" and "answer" are required.
                if (empty($faqData['question']) || empty($faqData['answer'])) {
                    return response()->json([
                        'message' => 'For new FAQ entries, both question and answer are required.',
                        'status'  => 422,
                        'error'   => true,
                    ], 422);
                }
                $faq = new Faq();
                $faq->question = $faqData['question'];
                $faq->answer = $faqData['answer'];
            }

            $faq->save();
            $updatedFaqs[] = $faq;
        }

        return response()->json([
            'data'    => $updatedFaqs,
            'message' => 'FAQs updated and added successfully.',
            'status'  => 200,
            'error'   => false,
        ], 200);
    }


    // GetFaqList
    public function getAllFaq(Request $request)
    {
        $perPage = $request->query('per_page', 10);
        try {
            if ($request->has('paginate') && $request->paginate == 'true') {
                $faq = Faq::paginate($perPage);
            } else {

                $faq = Faq::all();
            }

            return response()->json([
                'data' => $faq,
                'message' => 'List Of FAQ retrieved successfully',
                'status' => 200,
                'error' => false,
            ]);
        } catch (\Exception $e) {
            return $this->sendError('Error.', $e->getMessage());
        }
    }

    //get Faq by id 
    public function getFaqDetailsById($faqId)
    {
        $faqDetails = Faq::where('id', $faqId)->get();

        return response()->json([
            'data' => $faqDetails,
            'message' => 'Faq Details Retrieved Successfully',
            'status' => 200,
            'error' => false,
        ], 200);
    }
}
