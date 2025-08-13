<?php

namespace App\Http\Controllers;

use App\Models\Faculty;
use App\Models\StudyProgram;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class FacultyController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $perPage = $request->get('per_page', 10);
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');

        $query = Faculty::withCount('studyPrograms');

        // Search functionality
        if ($search) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        // Sorting
        $query->orderBy($sortBy, $sortOrder);

        $faculties = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Faculties/Index', [
            'faculties' => $faculties,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ]
        ]);
    }

    public function create()
    {
        return Inertia::render('Faculties/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:faculties,name',
        ]);

        Faculty::create([
            'name' => $request->name,
        ]);

        return redirect()->route('faculties.index')
            ->with('success', 'Faculty created successfully.');
    }

    public function show(Faculty $faculty)
    {
        $faculty->load([
            'studyPrograms' => function ($query) {
                $query->orderBy('name');
            }
        ]);

        return Inertia::render('Faculties/Show', [
            'faculty' => $faculty,
        ]);
    }

    public function edit(Faculty $faculty)
    {
        return Inertia::render('Faculties/Edit', [
            'faculty' => $faculty,
        ]);
    }

    public function update(Request $request, Faculty $faculty)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:faculties,name,' . $faculty->id,
        ]);

        $faculty->update([
            'name' => $request->name,
        ]);

        return redirect()->route('faculties.index')
            ->with('success', 'Faculty updated successfully.');
    }

    public function destroy(Faculty $faculty)
    {
        try {
            // Check if faculty has study programs
            if ($faculty->studyPrograms()->exists()) {
                return back()->withErrors(['error' => 'Cannot delete faculty that has study programs.']);
            }

            $faculty->delete();

            return redirect()->route('faculties.index')
                ->with('success', 'Faculty deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete faculty: ' . $e->getMessage()]);
        }
    }

    // Study Program methods
    public function studyPrograms(Request $request)
    {
        $search = $request->get('search');
        $facultyFilter = $request->get('faculty_id');
        $perPage = $request->get('per_page', 10);
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');

        $query = StudyProgram::with('faculty');

        // Search functionality
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhereHas('faculty', function ($facultyQuery) use ($search) {
                        $facultyQuery->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        // Faculty filter
        if ($facultyFilter) {
            $query->where('faculty_id', $facultyFilter);
        }

        // Sorting
        if ($sortBy === 'faculty_name') {
            $query->join('faculties', 'study_programs.faculty_id', '=', 'faculties.id')
                ->orderBy('faculties.name', $sortOrder)
                ->select('study_programs.*');
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $studyPrograms = $query->paginate($perPage)->withQueryString();
        $faculties = Faculty::orderBy('name')->get(['id', 'name']);

        return Inertia::render('StudyPrograms/Index', [
            'studyPrograms' => $studyPrograms,
            'faculties' => $faculties,
            'filters' => [
                'search' => $search,
                'faculty_id' => $facultyFilter,
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ]
        ]);
    }

    public function createStudyProgram()
    {
        $faculties = Faculty::orderBy('name')->get(['id', 'name']);

        return Inertia::render('StudyPrograms/Create', [
            'faculties' => $faculties,
        ]);
    }

    public function storeStudyProgram(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'faculty_id' => 'required|exists:faculties,id',
        ]);

        // Check if study program name is unique within the faculty
        $exists = StudyProgram::where('faculty_id', $request->faculty_id)
            ->where('name', $request->name)
            ->exists();

        if ($exists) {
            return back()->withErrors(['name' => 'Study program name must be unique within the faculty.']);
        }

        StudyProgram::create([
            'name' => $request->name,
            'faculty_id' => $request->faculty_id,
        ]);

        return redirect()->route('faculties.study-programs')
            ->with('success', 'Study program created successfully.');
    }

    public function editStudyProgram(StudyProgram $studyProgram)
    {
        $faculties = Faculty::orderBy('name')->get(['id', 'name']);

        return Inertia::render('StudyPrograms/Edit', [
            'studyProgram' => $studyProgram->load('faculty'),
            'faculties' => $faculties,
        ]);
    }

    public function updateStudyProgram(Request $request, StudyProgram $studyProgram)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'faculty_id' => 'required|exists:faculties,id',
        ]);

        // Check if study program name is unique within the faculty (excluding current record)
        $exists = StudyProgram::where('faculty_id', $request->faculty_id)
            ->where('name', $request->name)
            ->where('id', '!=', $studyProgram->id)
            ->exists();

        if ($exists) {
            return back()->withErrors(['name' => 'Study program name must be unique within the faculty.']);
        }

        $studyProgram->update([
            'name' => $request->name,
            'faculty_id' => $request->faculty_id,
        ]);

        return redirect()->route('faculties.study-programs')
            ->with('success', 'Study program updated successfully.');
    }

    public function destroyStudyProgram(StudyProgram $studyProgram)
    {
        try {
            // Check if study program has reviewers
            if ($studyProgram->reviewers()->exists()) {
                return back()->withErrors(['error' => 'Cannot delete study program that has reviewers.']);
            }

            $studyProgram->delete();

            return redirect()->route('faculties.study-programs')
                ->with('success', 'Study program deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete study program: ' . $e->getMessage()]);
        }
    }
}
