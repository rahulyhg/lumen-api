<?php

namespace App\Http\Controllers;

use App\UserQuestion as UserQuestion;
use App\User as User;
use App\Question as Question;
use App\UserFile as UserFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use App\Http\Controllers\CategoryController;
use App\Category as Category;
use phpDocumentor\Reflection\Types\Null_;
use Illuminate\Http\Request;
use Psy\Util\Json;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\UserYear;
use App\Child;
use Intervention\Image\Facades\Image as Image;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class QuestionController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function getQuestions(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        // var_dump($request->user_year);

        if (empty($request->input('user_year'))) {
            $year = $request->input('year');

            $partner = $user->getPartner()
            ->first()
            ->getInfo()
            ->first();

            $children = [];
            foreach ($user->getChildren()->get() as $child) {
                array_push($children, $child->getInfo()->first());
            }

            $userYear = UserYear::where("person_id", "=", $user->person_id)
            ->where("year_id", "=", $year)
            ->first();
        } else {
            if ($user->role == 2 || $user->role == 3) {
                $userYear = UserYear::where("id", "=", $request->input('user_year'))
                    ->first();

                $year = $userYear->year_id;
                    


                $userToReview = User::where("person_id", "=", $userYear->person_id)
                    ->first();


                $partner = $userToReview->getPartner()
                    ->first()
                    ->getInfo()
                    ->first();

                $children = [];
                foreach ($userToReview->getChildren()->get() as $child) {
                    array_push($children, $child->getInfo()->first());
                }
            }
        }



        $categoryController = new CategoryController();
        $categories = $categoryController->getCategoriesByYear($year);

        $questionaire = [];

        foreach ($categories as $category) {
            $groups = $category
                ->getGroups()
                ->get();

            $g = array();

            foreach ($groups as $group) {
                $questions = $group->getQuestions()
                    ->leftjoin('user_question', function ($join) use ($userYear) {
                        $join->on('question.id', '=', 'user_question.question_id');
                        $join->on('user_question.user_year_id', "=", DB::raw($userYear->id));
                    })
                    ->leftjoin('feedback', 'user_question.id', 'feedback.user_question_id')
                    ->leftjoin('user_file', function ($join) use ($userYear) {
                        $join->on('question.id', '=', 'user_file.question_id');
                        $join->on('user_file.user_year_id', "=", DB::raw($userYear->id));
                    })
//                    ->leftjoin('user_year', 'user_question.user_year_id', 'user_year.id')
//                    ->leftjoin('user', 'user_year.person_id', 'user.person_id')
//                    ->leftjoin('partner', 'user.person_id', 'partner.user_id')
//                    ->leftjoin('person as personpartner', 'partner.person_id', 'personpartner.id')
//                    ->leftjoin('child', 'user.person_id', 'child.user_id')
//                    ->leftjoin('person as personchild', 'child.person_id', 'personchild.id')
                    ->groupBy('question.id')
                    ->select('question.id', 'question.text', 'question.group_id', 'question.condition', 'question.type', 'question.answer_option', 'question.parent', 'question.has_childs', 'user_question.question_answer as answer', DB::raw("group_concat(`user_file`.`name` SEPARATOR '|;|') as `file_names`"), 'user_question.approved', 'feedback.text as feedback')
//                    ->select('question.id', 'question.text', 'question.group_id', 'question.condition', 'question.type', 'question.answer_option', 'question.parent', 'question.has_childs', 'user_question.question_answer as answer', 'personpartner.first_name as partner_first_name', DB::raw("group_concat(`personchild`.`first_name` SEPARATOR '|;|') as `child_first_name`"), DB::raw("group_concat(`user_file`.`name` SEPARATOR '|;|') as `file_names`"), 'user_question.approved', 'feedback.text as feedback')
                    ->orderBy('question.id', 'asc')
                    ->get();

                $q = array();

                foreach ($questions as $question) {
                    if (strpos($question->child_first_name, '|;|') !== false) {
                        $question->child_first_name = explode('|;|', $question->child_first_name);
                    }
                    if ($question->child_first_name === null) {
                        $question->child_first_name = [];
                    }

                    if (strpos($question->file_names, '|;|') !== false) {
                        $question->file_names = explode('|;|', $question->file_names);
                    }
                    if ($question->file_names === null) {
                        $question->file_names = [];
                    }
                    if (empty($question->parent)) {
                        $this->getChildren($question, $userYear);

                        array_push($q, $question);
                    }
                }

                unset($group->category_id);
                $group['questions'] = $q;
                array_push($g, $group);
            }

            array_push(
                $questionaire, array(
                    'id' => $category->id,
                    'name' => $category->name,
                    'year_id' => $category->year_id,
                    'question_id' => $category->question_id,
                    'condition' => $category->condition,
                    'groups' => $g
                )
            );
        }

        return new Response(array(
            "categories" => $questionaire,
            "partner" => $partner,
            "children" => $children
        ));
    }

    function getChildren($question, $userYear)
    {
        if ($question->answer_option == 1) {
            $question['answer_options'] = $question->getOptions()->pluck('text')->toArray();
        } else {
            $question['answer_options'] = null;
        }

        if ($question->has_childs) {
            $children = [];

            $childs = $question->getChilds()
                ->leftjoin('user_question', function ($join) use ($userYear) {
                    $join->on('question.id', '=', 'user_question.question_id');
                    $join->on('user_question.user_year_id', "=", DB::raw($userYear->id));
                })
                ->leftjoin('feedback', 'user_question.id', 'feedback.user_question_id')
                ->leftjoin('user_file', function ($join) use ($userYear) {
                    $join->on('question.id', '=', 'user_file.question_id');
                    $join->on('user_file.user_year_id', "=", DB::raw($userYear->id));
                })
                ->groupBy('question.id')
                ->select('question.id', 'question.text', 'question.group_id', 'question.condition', 'question.type', 'question.answer_option', 'question.parent', 'question.has_childs', 'user_question.question_answer as answer', DB::raw("group_concat(`user_file`.`name` SEPARATOR '|;|') as `file_names`"), 'user_question.approved', 'feedback.text as feedback')
                ->orderBy('question.id', 'asc')
                ->get();

            foreach ($childs as $child) {
                if (strpos($child->file_names, '|;|') !== false) {
                    $child->file_names = explode('|;|', $question->file_names);
                }
                if ($child->file_names === null) {
                    $child->file_names = [];
                }
                array_push($children, $child);
                $this->getChildren($child, $userYear);

                unset($question['answer_option']);
                unset($question['parent']);
                unset($question['has_childs']);
            }

            $question['children'] = $children;
        } else {
            unset($question['answer_option']);
            unset($question['parent']);
            unset($question['has_childs']);

            $question['children'] = null;

            return $question;
        }
    }

    public function saveFileQuestion(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $year = $request->input('year');
        $questionId = $request->input('id');
        $userYear = UserYear::where("person_id", "=", $user->person_id)
            ->where("year_id", "=", $year)->first();

        foreach ($request->file('files') as $file) {
            $pinfo = pathinfo($file->getClientOriginalName());
            Storage::putFileAs('userDocuments/' . $user->person_id, $file, $pinfo['filename'] . "_" . date("YmdHis") . $pinfo['extension']);

            $userFile = new UserFile();
            $userFile->user_year_id = $userYear->id;
            $userFile->person_id = $user->person_id;
            $userFile->question_id = $questionId;
            $userFile->name = $file->getClientOriginalName();
            $userFile->type = 10;

            $userFile->save();
        }
        return $year;
    }

    public function saveQuestion(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $year = $request->input('year');
        $questionId = $request->input('id');
        $answer = $request->input('answer');
        $userYear = UserYear::where("person_id", "=", $user->person_id)
            ->where("year_id", "=", $year)->first();

        $existingQuestion = $this->checkQuestion($userYear, $questionId);

        if (isset($existingQuestion)) {
            $existingQuestion->question_answer = $answer;
            $existingQuestion->save();
        } else {
            $userQuestion = new UserQuestion();
            $userQuestion->user_year_id = $userYear->id;
            $userQuestion->question_id = $questionId;
            $userQuestion->question_answer = $answer;

            $userQuestion->save();
        }

        return $year;
    }

    public function checkQuestion($userYear, $questionId)
    {
        return UserQuestion::where("user_year_id", "=", $userYear->id)->where("question_id", "=", $questionId)->first();
    }
}
