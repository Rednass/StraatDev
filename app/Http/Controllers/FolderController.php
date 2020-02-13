<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Repository\Eloquent\FolderRepository;
use App\Repository\Eloquent\TipRepository;
use App\Repository\Eloquent\SavedLearningItemRepository;
use App\Repository\Eloquent\FolderCommentRepository;
use App\Folder;
use App\FolderComment;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Services\CurrentUserResolver;
use Illuminate\Http\RedirectResponse;
use App\Tips\Services\TipEvaluator;

class FolderController extends Controller
{

    /**
     * @var CurrentUserResolver
     */
    private $currentUserResolver;

    /**
     * @var FolderRepository
     */
    private $folderRepository;

    /**
     * @var FolderCommentRepository
     */
    private $folderCommentRepository;

    /**
     * @var SavedLearningItemRepository
     */
    private $savedLearningItemRepository;

    /**
     * @var TipRepository
     */
    private $tipRepository;

    public function __construct(
        CurrentUserResolver $currentUserResolver,
        FolderRepository $folderRepository,
        TipRepository $tipRepository,
        SavedLearningItemRepository $savedLearningItemRepository,
        FolderCommentRepository $folderCommentRepository)
    {
        $this->currentUserResolver = $currentUserResolver;
        $this->folderRepository = $folderRepository;
        $this->folderCommentRepository = $folderCommentRepository;
        $this->savedLearningItemRepository = $savedLearningItemRepository;
        $this->tipRepository = $tipRepository;
    }

    public function index(TipEvaluator $evaluator)
    {
        $student = $this->currentUserResolver->getCurrentUser();
        $tips = $this->tipRepository->all();
        $sli = $this->savedLearningItemRepository->findByStudentnr($student->student_id);

        $evaluatedTips = [];
        foreach ($tips as $tip) {
            $evaluatedTips[$tip->id] = $evaluator->evaluateForChosenStudent($tip, $student);
        }

        return view('pages.folders')
            ->with('student', $student)
            ->with('sli', $sli)
            ->with('evaluatedTips', $evaluatedTips);
        $this->savedLearningItemRepository = $savedLearningItemRepository;
    }

    public function create(Request $request)
    {
        $student = $this->currentUserResolver->getCurrentUser();

        $folder = new Folder();
        $folder->title = $request['folder_title'];
        $folder->description = $request['folder_description'];
        $folder->student_id = $student->student_id;

        $this->folderRepository->save($folder);

        session()->flash('success', __('folder.folder-created'));

        return redirect('folders');
    }

    public function shareFolderWithTeacher(Request $request)
    {
        $student = $this->currentUserResolver->getCurrentUser();
        $folder = Folder::find($request['folder_id']);

        if (!$student->is($folder->student)) {
            return redirect('saved-learning-items')->with('error', __('folder.share-permission'));
        }

        $folderComment = new FolderComment();
        $folderComment->text = $request['folder_comment'];
        $folderComment->folder_id = $request['folder_id'];
        $folderComment->author_id = $student->student_id;
        $this->folderCommentRepository->save($folderComment);


        $folder = $this->folderRepository->findById($request['folder_id']);
        $folder->teacher_id = $request['teacher'];
        $folder->save();

        session()->flash('success', __('folder.folder-shared'));

        return redirect('folders');
    }

    public function delete(Folder $folder): RedirectResponse
    {
        $student = $this->currentUserResolver->getCurrentUser();

        if (!$student->is($folder->student)) {
            return redirect('saved-learning-items')->with('error', __('folder.no-delete-permission'));
        }

        // remove all items from the folder
        foreach ($folder->savedLearningItems as $sli) {
            $sli->folder = null;
            $sli->save();
        }

        $this->folderRepository->delete($folder);
        session()->flash('success', __('folder.folder-deleted'));

        return redirect('folders');
    }

    public function addComment(Request $request) {
        $currentUser = $this->currentUserResolver->getCurrentUser();

        $folder = $this->folderRepository->findById($request['folder_id']);
        $student_id = $folder->student_id;

        $folderComment = new FolderComment();
        $folderComment->text = $request['folder_comment'];
        $folderComment->folder_id = $request['folder_id'];
        $folderComment->author_id = $currentUser->student_id;
        $this->folderCommentRepository->save($folderComment);

        if ($currentUser->isTeacher()) {
            $url = route('teacher-student-details', ['student' => $student_id]);
        } elseif ($currentUser->isStudent()) {
            $url = route('folders');
        }


        // fixme: what if user is admin? or shouldnt come here in that case
        return redirect($url);
    }

    public function stopSharingFolder(Folder $folder) {
        $student = $this->currentUserResolver->getCurrentUser();

        if (!$student->is($folder->student)) {
            return redirect('saved-learning-items')->with('error', __('folder.share-permission'));
        }

        $folder->teacher_id = null;
        $this->folderRepository->save($folder);

        return redirect('folders');
    }

    public function AddItemsToFolder(Request $request)
    {
        foreach ($request['check_list'] as $selectedItem) {
            $savedLearningItem =  $this->savedLearningItemRepository->findById($selectedItem);
            $savedLearningItem->folder =  $request['selected_folder_id'];
            $savedLearningItem->save();
        }

        return redirect('saved-learning-items');
    }
}