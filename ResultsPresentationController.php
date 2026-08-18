<?php

namespace App\Http\Controllers\MarksManagement;

use App\Events\ErrorLogEvent;
use App\Http\Controllers\Controller;
use App\Models\FailedModule;
use App\Models\MarksManagement\ExamCandidate;
use App\Models\MarksManagement\ExamMark;
use App\Models\MarksManagement\ExamSession;
use App\Models\StaffMember;
use App\Models\MarksManagement\StudentPartRemark;
use App\Models\ApplicationManagement\U1AcademicSession;
use App\Models\ProgrammeManagement\AdminStructure;
use App\Models\ProgrammeManagement\ProgrammeDefinition;
use App\Models\RegistrationManagement\StudentProgrammeStatus;
use DOMDocument;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use \PDF;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;


class ResultsPresentationController extends Controller
{
    private const PASS_MARK = 49.5;

    private function decryptRouteValue(string $value): mixed
    {
        try {
            return Crypt::decrypt($value);
        } catch (DecryptException $exception) {
            ErrorLogEvent::dispatch($exception);
            abort(404);
        }
    }

    private function examStages(): array
    {
        if (Gate::allows('is-registrar')) {
            return [3, null, null];
        }

        if (Gate::allows('is-dean')) {
            return [1, 2, 3];
        }

        if (Gate::allows('is-chairperson-alone')) {
            return [1, 2, 3];
        }

        return [null, null, null];
    }

    public function filter_results($state, $academic){
        $state = $this->decryptRouteValue($state);

        try {
            $department_id = StaffMember::where('national_id_number', Auth::user()->national_id_number)->first();

            $programmes = "";
            $academic_session = U1AcademicSession::has('exam_session')->where('status', 'Active')->get();
            if(Gate::allows('is-dean') ){
                //
                $departments = AdminStructure::where('parent_id', $department_id->admin_structure_fk_id)->pluck('id')->push($department_id->admin_structure_fk_id);
                $programmes = ProgrammeDefinition::where('status','active')->whereIn('adminStructCode', $departments)->get();

            }elseif (Gate::allows('is-registrar')){
                $programmes = ProgrammeDefinition::where('status','active')->get();
            }
            else{

                $programmes = ProgrammeDefinition::where('status','active')->where('adminStructCode', $department_id->admin_structure_fk_id)->get();
            }


            return view('exams_management.chairperson.student_marks_profile_report',compact('programmes','academic_session', 'academic', 'state'));
        } catch (\Throwable $exception) {
            ErrorLogEvent::dispatch($exception);
            return redirect()->back()->with('toast_error', 'Unable to load results. Please try again.');
        }
    }

    private function generate_html_view( $programme_code, $year_of_study, $semester_of_study, $exam_session_id,$exam_type, $state, $academic, $blade_viev){
       // try {

            $session = ExamSession::find($exam_session_id)->academic_session->academic_session_name;
            [$exam_stage_1, $exam_stage_2, $exam_stage_3] = $this->examStages();

            if($semester_of_study == 2 && $blade_viev){
            $students = DB::table('tblexam_marks')
                ->leftJoin('tbl_student_part_aggregate', 'tblexam_marks.student_number', '=', 'tbl_student_part_aggregate.student_number')
                ->leftJoin('tbl_student_part_remark', function ($join) use ($year_of_study, $semester_of_study) {
                    $join->on('tblexam_marks.student_number', '=', 'tbl_student_part_remark.student_number')
                        ->where('tbl_student_part_remark.year_of_study', '=', $year_of_study)
                        ->where('tbl_student_part_remark.semester_of_study', '=', $semester_of_study);
                })
                ->leftJoin('studentmember', 'tblexam_marks.student_number', '=', 'studentmember.studentNumber')
                ->select(
                    'tblexam_marks.student_number',
                    'tblexam_marks.programme_code',
                    'tblexam_marks.module_code',
                    'tblexam_marks.coursework_mark',
                    'tblexam_marks.exam_mark',
                    'tblexam_marks.overall_mark',
                    'tblexam_marks.exam_marks_stage',
                    'tblexam_marks.grade',
                    'tblexam_marks.publish_status',
                    DB::raw('COALESCE(tbl_student_part_aggregate.p1_grade, null) AS p1_avg'),
                    DB::raw('COALESCE(tbl_student_part_aggregate.p2_grade, null) AS p2_avg'),
                    DB::raw('COALESCE(tbl_student_part_aggregate.p3_grade, null) AS p3_avg'),
                    DB::raw('COALESCE(tbl_student_part_aggregate.p4_grade, null) AS p4_avg'),
                    DB::raw('COALESCE(tbl_student_part_aggregate.p5_grade, null) AS p5_avg'),
                    'tbl_student_part_aggregate.overall_avg',
                    'tbl_student_part_aggregate.degree_class',
                    'tbl_student_part_remark.remark',
                    'studentmember.firstName',
                    'studentmember.lastName',
                    'tblexam_marks.year_of_study',
                    'tblexam_marks.semester_of_study',
                    'tblexam_marks.exam_session_id'
                )
                ->where(function ($query) use ($programme_code, $year_of_study, $semester_of_study, $exam_session_id, $exam_type) {
                    $query->where([
                        'tblexam_marks.programme_code' => $programme_code,
                        'tblexam_marks.year_of_study' => $year_of_study,
                        'tblexam_marks.exam_type' => $exam_type,
                        'tblexam_marks.semester_of_study' => $semester_of_study,
                        'tblexam_marks.exam_session_id' => $exam_session_id,
                    ])->orWhere(function ($previousSemester) use ($programme_code, $exam_session_id, $year_of_study, $exam_type) {
                        $previousSemester->where('tblexam_marks.programme_code', $programme_code)
                            ->where('tblexam_marks.year_of_study', $year_of_study)
                            ->where('tblexam_marks.exam_type', $exam_type)
                            ->where('tblexam_marks.semester_of_study', 1)
                            ->where('tblexam_marks.exam_session_id', $exam_session_id - 1);
                    });
                })

               ->orderBy('tblexam_marks.semester_of_study')
               ->orderBy('tblexam_marks.publish_status', 'desc')
               ->orderBy('tblexam_marks.module_code')
                ->orderBy('studentmember.studentNumber')
                ->get();
            }else{
                $students = DB::table('tblexam_marks')
                ->leftJoin('tbl_student_part_aggregate', 'tblexam_marks.student_number', '=', 'tbl_student_part_aggregate.student_number')
                ->leftJoin('tbl_student_part_remark', function ($join) use ($year_of_study, $semester_of_study) {
                    $join->on('tblexam_marks.student_number', '=', 'tbl_student_part_remark.student_number')
                        ->where('tbl_student_part_remark.year_of_study', '=', $year_of_study)
                        ->where('tbl_student_part_remark.semester_of_study', '=', $semester_of_study);
                })
                ->leftJoin('studentmember', 'tblexam_marks.student_number', '=', 'studentNumber')
                ->select(
                    'tblexam_marks.student_number',
                    'tblexam_marks.programme_code',
                    'tblexam_marks.module_code',
                    'tblexam_marks.coursework_mark',
                    'tblexam_marks.exam_mark',
                    'tblexam_marks.overall_mark',
                    'tblexam_marks.exam_marks_stage',
                    'tblexam_marks.grade',
                    'tblexam_marks.publish_status',
                    DB::raw('COALESCE(tbl_student_part_aggregate.p1_grade, null) AS p1_avg'),
                    DB::raw('COALESCE(tbl_student_part_aggregate.p2_grade, null) AS p2_avg'),
                    DB::raw('COALESCE(tbl_student_part_aggregate.p3_grade, null) AS p3_avg'),
                    DB::raw('COALESCE(tbl_student_part_aggregate.p4_grade, null) AS p4_avg'),
                    DB::raw('COALESCE(tbl_student_part_aggregate.p5_grade, null) AS p5_avg'),
                    'tbl_student_part_aggregate.overall_avg',
                    'tbl_student_part_aggregate.degree_class',
                    'tbl_student_part_remark.remark',
                    'firstName',
                    'lastName',
                    'tblexam_marks.year_of_study',
                    'tblexam_marks.semester_of_study',
                    'tblexam_marks.exam_session_id'
                )
                ->where([
                    'tblexam_marks.programme_code' => $programme_code,
                    'tblexam_marks.year_of_study' => $year_of_study,
                    'tblexam_marks.semester_of_study' => $semester_of_study,
                    'tblexam_marks.exam_session_id' => $exam_session_id,
                    'exam_type' => $exam_type

//                    'exam_marks_stage' => $exam_stage
                ])
                // ->orderBy('lastName')
                ->orderBy('tblexam_marks.semester_of_study')
               ->orderBy('tblexam_marks.publish_status', 'desc')
               ->orderBy('tblexam_marks.module_code')
                ->orderBy('studentmember.studentNumber')
                ->get();
            }


            if($students->isEmpty()){
                return null;

            }
            $htmlTable = '<table style="border-collapse: collapse;" >';
            $htmlTable .= '<thead>';

              $headClass = " ";
              $headSClass = " ";
              if($state <> 3){
                  $headClass = "text-white bg-red-600";
                  $headSClass = "text-white bg-green-600";
              }

            $partAggregateColumn = 'Part ' . $students[0]->year_of_study . ' Aggr';
            $htmlTable .= '<tr style="border: 1px solid black;" class="'.$headClass.'">';
            $htmlTable .= '<th rowspan="2" style="border: 1px solid black; width: 2px;">No.</th>';
             $htmlTable .= '<th rowspan="2" style="border: 1px solid black;">Surname</th>';
            $htmlTable .= '<th rowspan="2" style="border: 1px solid black;">Firstname</th>';
            $htmlTable .= '<th rowspan="2" style="border-right: 2px solid black;">Reg No</th>';

            // $moduleCodes = $students->sortBy('exam_session_id')->sortBy('module_code')->sortByDesc('publish_status')->pluck('module_code')->unique();
            // $moduleCodes = $students->pluck('module_code')->unique();
            $moduleCodes = $students->unique('module_code');
            foreach ($moduleCodes as $moduleCode) {
            //   if($moduleCode->exam_session_id < $exam_session_id){
            //     $htmlTable .= '<th colspan="3" style="border-right: 2px solid black;"  class="bg-green-200 text-green-800">' . $moduleCode->module_code . '</th>';
            // }else{
                $htmlTable .= '<th colspan="3" style="border-right: 2px solid black;">' . $moduleCode->module_code . '</th>';
           // }
            }

            $htmlTable .= '<th rowspan="2" style="border: 1px solid black;">' . $partAggregateColumn . '</th>';
            $htmlTable .= '<th rowspan="2" style="border: 1px solid black;">Ov Aggre</th>';
            $htmlTable .= '<th rowspan="2" style="border: 1px solid black;">Class </th>';
            $htmlTable .= '<th rowspan="2" style="border: 1px solid black;">Remarks</th>';
            $htmlTable .= '<th rowspan="2" style="border: 1px solid black;">Owed Modules</th>';

            $htmlTable .= '</tr>';
            $htmlTable .= '<tr class="'.$headSClass.'">';
            foreach ($moduleCodes as $moduleCode) {
             if($moduleCode->exam_session_id < $exam_session_id){
             $htmlTable .= '<th style="border: 1px solid black;" class="bg-green-200 text-green-800">CW</th>
                            <th style="border: 1px solid black;" class="bg-green-200 text-green-800">EX</th>
                            <th style="border-right: 2px solid black;" class="bg-green-200 text-green-800">OM</th>';
            } else{
               $htmlTable .= '<th style="border: 1px solid black;">CW</th>
                            <th style="border: 1px solid black;">EX</th>
                            <th style="border-right: 2px solid black;">OM</th>';
                }
            }
            $htmlTable .= '</tr>';
            $htmlTable .= '</thead>';
            $htmlTable .= '<tbody>';

            $processedStudents = [];
            $count = 1;

            foreach ($students as $student) {

                $cleanString = $this->pendingFailed($student->student_number, $student->remark);

                // Check if student has already been processed
                if (in_array($student->student_number, $processedStudents)) {
                    continue; // Skip to next iteration if duplicate
                }

                $htmlTable .= '<tr style="border: 1px solid black;">';
                $htmlTable .= '<td style="border: 1px solid black; width: 2px">' .  $count++ . '</td>';
                $htmlTable .= '<td style="border: 1px solid black; text-transform: uppercase;">' . $student->lastName . '</td>';
                $htmlTable .= '<td style="border: 1px solid black; text-transform: uppercase;">' . $student->firstName.  '</td>';
                $htmlTable .= '<td style="border-right: 2px solid black; color: #0a53be">' .'<a style="text-decoration: none; text-transform: uppercase;" target="_blank" href="'. route('examshared.full-results-profile', [encrypt(''),$student->student_number]) . '">' .$student->student_number . '</a>'. '</td>';
                foreach ($moduleCodes as $moduleCode) {
                    $moduleMark = $students
                        ->where('student_number', $student->student_number)
                        ->where('module_code', $moduleCode->module_code)
                        ->first();

                   // $htmlTable .= '<td class="exam-center" style="text-align: center">';
                    if ($moduleMark) {
                        if($moduleMark->exam_marks_stage < intval($state)){
                            $ca = '';
                            $ex = '';
                            $om = '';
                        }else{
                            $ca =round($moduleMark->coursework_mark )?? '-';
                            $ex = round($moduleMark->exam_mark) ?? '-';
                            $om = $moduleMark->overall_mark ?? '0';
                        }

                        $td = '<td class="text-center">';
                        $tdOV = '<td class="border-r-2 border-black text-center" style="border-right: 2px solid black;">';
                         if ($moduleMark->overall_mark < 50) {
                                $td = '<td class="text-center text-red-600">';
                                $tdOV = '<td class="border-r-2 border-black text-center text-red-600" style="border-right: 2px solid black;">';
                            }
                        if($moduleMark->exam_marks_stage < 3){
                            if($moduleMark->exam_marks_stage == $state){
                                 $td = '<td class="text-center bg-blue-400">';
                                $tdOV = '<td class="border-r-2 border-black text-center bg-blue-400" style="border-right: 2px solid black;">';
                                if ($moduleMark->overall_mark < 50) {
                                        $td = '<td class="text-center text-red-600 bg-blue-400">';
                                        $tdOV = '<td class="border-r-2 border-black text-center text-red-600 bg-blue-400" style="border-right: 2px solid black;">';
                                    }
                            }elseif($moduleMark->exam_marks_stage == intval($state) - 1 ){
                                $td = '<td class="text-center bg-pink-500 text-pink-500">';
                                $tdOV = '<td class="border-r-2 border-black text-center bg-pink-500 text-pink-500" style="border-right: 2px solid black;">';
                            }elseif($moduleMark->exam_marks_stage == intval($state) - 2 ){
                                $td = '<td class="text-center bg-purple-300 text-purple-300">';
                                $tdOV = '<td class="text-center bg-purple-300 text-purple-300" style="border-right: 2px solid black;">';
                            }

                        }

                        if ($moduleMark->publish_status) {
                            $td = '<td class="text-center bg-gray-400">';
                            $tdOV = '<td class="text-center bg-gray-400" style="border-right: 2px solid black;">';
                            if ($moduleMark->overall_mark < 50) {
                                $td = '<td class="text-center bg-gray-400 text-red-600" >';
                                $tdOV = '<td class="text-center bg-gray-400 text-red-600" style="border-right: 2px solid black;">';
                            }
                        }

                        if ($moduleMark->exam_session_id != $exam_session_id) {
                            $td = '<td class="text-center bg-green-200">';
                            $tdOV = '<td class="text-center bg-green-200" style="border-right: 2px solid black;">';
                            if ($moduleMark->overall_mark < 50) {
                                $td = '<td class="text-center bg-green-200 text-red-600" >';
                                $tdOV = '<td class="text-center bg-green-200 text-red-600" style="border-right: 2px solid black;">';
                            }
                        }
                       // $htmlTable .= '<td style="text-align: center; background-color: #dedede">';
                        $htmlTable .= $td . $ca. '</td>';
                        $htmlTable .=  $td . $ex . '</td>';
                        $htmlTable .=  $tdOV . $om . '</td>';

                    } else {
                        // No matching record found, display 'N/A'
                        $htmlTable .= '<td style="text-align: center">-' .'</td>';
                         $htmlTable .= '<td style="text-align: center">-' .'</td>';
                        $htmlTable .= '<td style="border-right: 2px solid black; text-align: center">-' . '</td>';
                    }
                }
                 $pt = 'p' . $student->year_of_study . '_avg';
                 if (!$student->remark){
                   $status = StudentProgrammeStatus::where(['studentNumber' => $student->student_number, 'session'=>$session])->where('status', '!=', 'REGISTERED')->first();
                   if($status){
                    $remark = ($status->status == 'UNREGISTERED') ? 'PRESUMED WITHDRAWN' : $status->status;

                   } else {$remark="";}
                 } else {$remark= $student->remark;}


                $htmlTable .= '<td style="text-align: center">' . $student->$pt . '</td>';
                $htmlTable .= '<td style="text-align: center">' . $student->overall_avg . '</td>';
                $htmlTable .= '<td style="border-right: 2px solid black; text-align: center">' . $student->degree_class . '</td>';
                $htmlTable .= '<td style="border-right: 2px solid black; text-align: center; text-transform: uppercase;">' . $remark . '</td>';
                $htmlTable .= '<td style="text-align: center">' . $cleanString . '</td>';

                $htmlTable .= '</tr>';

                // Add the student to the processed list
                $processedStudents[] = $student->student_number;
            }

            $htmlTable .= '</tbody>';
            $htmlTable .= '</table>';

            return  $htmlTable;
    }

    private function pendingFailed($studentmember, $remark)
    {
        $cleaned =  $this->owedModules($studentmember);

        // Extract error codes from the remark string with any prefix followed by four digits
        preg_match_all('/\b\w{3}\d{4}\b/', $remark, $matches);
        $remarkCodes = $matches[0]; // This will be an array of error codes

        // Find elements in $cleaned that are not in $remarkCodes
        $notInRemarks = array_diff($cleaned, $remarkCodes);

        $pendingFailed = collect($notInRemarks)->unique()->values()->all();

        return $cleanString = implode(' ', $pendingFailed);
    }

    private function owedModules($studentmember)
    {
        $failed = collect(ExamMark::where('student_number',$studentmember)->where('overall_mark', '<', self::PASS_MARK)->pluck('module_code'));
       $cleaned = array();
        $passed = collect(ExamMark::where('student_number', $studentmember)->where('overall_mark', '>=', self::PASS_MARK)->pluck('module_code'))->toArray();
        if($failed->count()){
            $cleaned =  array_diff($failed->toArray(), $passed);
        }
            return $cleaned;

    }

    public function results_presentation(Request $request)
    {
        $validated = $request->validate([
            'programme_code' => ['required', 'string'],
            'year_of_study' => ['required', 'integer', 'min:1'],
            'semester_of_study' => ['required', 'integer', 'between:1,2'],
            'exam_session_id' => ['required', 'string'],
            'exam_type' => ['nullable', 'string', 'max:100'],
            'state' => ['required', 'integer'],
            'academic' => ['required', 'integer'],
        ]);

        $programme_code = $this->decryptRouteValue($validated['programme_code']);
        $year_of_study = $validated['year_of_study'];
        $semester_of_study = $validated['semester_of_study'];
        $exam_session_id = $this->decryptRouteValue($validated['exam_session_id']);
        $semester_one_profile = null;
        $exam_type = $validated['exam_type'] ?? null;
        $state = $validated['state'];
        $academic = $validated['academic'];
        $sessionname = ExamSession::find($exam_session_id)->academic_session->academic_session_name;
        [$exam_stage_1, $exam_stage_2, $exam_stage_3] = $this->examStages();
        //try {

            // Set CSS styles for the table
            $cssStyles = '
            <style>
                table {
                    width: 100%;
                    border-collapse: collapse;

                }

                .exam-center{
                align-items: center;
                }

                /* Adjust font size based on content */
               table th, table td {
                    font-size: 10px;
                }

                /* Add more CSS styles as needed */
                /* ... */
            </style>
        ';
           // if($semester_of_study == 2){
           //  $session_id = $exam_session_id -1;
           //     $semester_one_profile =  $this->generate_html_view($programme_code, $year_of_study,1,$exam_session_id, $exam_type, $state, $academic);
           // }
            $htmlTable =  $this->generate_html_view($programme_code, $year_of_study,$semester_of_study,$exam_session_id,$exam_type, $state, $academic,1);


            $carry_over_modules = $this->carry_over_modules($exam_session_id,$programme_code,$year_of_study,$semester_of_study);

            $semester_one_profile_1 = $cssStyles . $semester_one_profile;

            $programme_name = ProgrammeDefinition::where('status','active')->where('programmeCode', $programme_code)->first()->programmeName;
            $total_students = ExamMark::select('student_number')->where([
                'tblexam_marks.programme_code' => $programme_code,
                'tblexam_marks.year_of_study' => $year_of_study,
                'tblexam_marks.semester_of_study' => $semester_of_study,
                'exam_session_id' => $exam_session_id
            ])
            //->pluck('student_number');
                ->groupBy('student_number')
                ->get();
            $all = StudentPartRemark::whereIn('student_number', $total_students)
                                        ->where(['year_of_study'=>$year_of_study,'semester_of_study'=>$semester_of_study])

                                        ->pluck('student_number');
            $totalStudents = StudentProgrammeStatus::where(['programmeCode'=>$programme_code,'session'=>$sessionname,'status'=>'REGISTERED','yearOfStudy'=>$year_of_study,'semesterOfStudy'=>$semester_of_study,'recordStatus'=>'CURRENT'])
                ->count();
                //->pluck('studentNumber');

            $exam_marks = ExamMark::where(['programme_code'=>$programme_code,'year_of_study'=>$year_of_study,
                'semester_of_study'=>$semester_of_study,'exam_session_id'=>$exam_session_id,'exam_type'=>$exam_type])//'exam_marks_stage'=>1,'exam_type'=>$exam_type
                ->whereHas('student_programme_status',function($query) use($programme_code,$year_of_study,$semester_of_study){
                    $query->where(['programmeCode'=>$programme_code,'yearOfStudy'=>$year_of_study,'semesterOfStudy'=>$semester_of_study,'recordStatus'=>'CURRENT']);
                })->get();


            $passing = StudentPartRemark::whereIn('student_number', $total_students)
                                        ->where(['year_of_study'=>$year_of_study,'semester_of_study'=>$semester_of_study])
                                        ->where(function ($query){
                                            $query->where('remark','like','%'. 'PASS'. '%')
                                                    ->orWhere('remark','like','%'. 'PENDING'. '%')
                                                ->orWhere('remark','=', 'PROCEED');
                                        })
                                        ->get();
             $fails  =  ExamMark::select('student_number')
                        ->where(['programme_code'=>$programme_code,'year_of_study'=>$year_of_study, 'semester_of_study'=>$semester_of_study,'exam_session_id'=>$exam_session_id,'exam_type'=>$exam_type])
                        ->groupBy('student_number')
                        ->havingRaw('MIN(COALESCE(overall_mark, 0)) < 50')
                        ->count();

            $passes = ExamMark::select('student_number')
                    ->where(['programme_code'=>$programme_code,'year_of_study'=>$year_of_study, 'semester_of_study'=>$semester_of_study,'exam_session_id'=>$exam_session_id, 'exam_type'=>$exam_type])
                    ->groupBy('student_number')
                    ->havingRaw('MIN(COALESCE(overall_mark, 0)) >= 50')
                    ->count();

            // Distinct students on the current programme + session + Part who are
            // sitting a module (`cur` row) they have written before in an earlier
            // session and failed (prior overall_mark < 50), never passed since
            // (no earlier row has overall_mark >= 50) AND the module's canonical
            // Part (tblmoduleprogrammestatus) is EARLIER than the current view's
            // Part — same-Part repeats are not carry-overs. NULL prior marks are
            // ignored (project modules like EMN3000 span two Parts with a NULL
            // placeholder — that isn't a failure).
            // Multiple carried modules for the same student count once.
            $carrying = DB::table('tblexam_marks as cur')
                ->join('tblmoduleprogrammestatus as mps', function ($join) {
                    $join->on('mps.moduleCode',    '=', 'cur.module_code')
                         ->on('mps.programmeCode', '=', 'cur.programme_code')
                         ->whereNull('mps.deleted_at');
                })
                ->where('cur.programme_code',    $programme_code)
                ->where('cur.exam_session_id',   $exam_session_id)
                ->where('cur.year_of_study',     $year_of_study)
                ->where('cur.semester_of_study', $semester_of_study)
                ->whereNull('cur.deleted_at')
                ->whereRaw('(mps.yearOfStudy * 10 + mps.semesterOfStudy) < ?',
                    [$year_of_study * 10 + $semester_of_study])
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                      ->from('tblexam_marks as prev')
                      ->whereColumn('prev.student_number', 'cur.student_number')
                      ->whereColumn('prev.module_code',    'cur.module_code')
                      ->whereColumn('prev.programme_code', 'cur.programme_code')
                      ->whereColumn('prev.exam_session_id','<', 'cur.exam_session_id')
                      ->where('prev.overall_mark', '<', 50)
                      ->whereNull('prev.deleted_at');
                })
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                      ->from('tblexam_marks as pass')
                      ->whereColumn('pass.student_number', 'cur.student_number')
                      ->whereColumn('pass.module_code',    'cur.module_code')
                      ->whereColumn('pass.programme_code', 'cur.programme_code')
                      ->whereColumn('pass.exam_session_id','<', 'cur.exam_session_id')
                      ->where('pass.overall_mark', '>=', 50)
                      ->whereNull('pass.deleted_at');
                })
                ->distinct()
                ->count('cur.student_number');


            // $carrying = StudentPartRemark::whereIn('student_number', $total_students)
            //     ->where(['year_of_study'=>$year_of_study,'semester_of_study'=>$semester_of_study])
            //     ->where('remark','like','%'. 'CARRY'. '%')
            //     ->get();

                //$cleaned =  $this->owedModules($studentmember);

            // Distinct students on the current programme + session + Part who are
            // sitting a module they wrote before in an earlier session, never
            // passed, and the module's canonical Part is the SAME as the current
            // view's Part — i.e. they are repeating a Part-level module (e.g.
            // MUZAPU wrote EMN2101 in Part 2.1 session 10, failed 39, now sits
            // Part 2.1 session 12 again).
            $repeating = collect(DB::table('tblexam_marks as cur')
                ->join('tblmoduleprogrammestatus as mps', function ($join) {
                    $join->on('mps.moduleCode',    '=', 'cur.module_code')
                         ->on('mps.programmeCode', '=', 'cur.programme_code')
                         ->whereNull('mps.deleted_at');
                })
                ->where('cur.programme_code',    $programme_code)
                ->where('cur.exam_session_id',   $exam_session_id)
                ->where('cur.year_of_study',     $year_of_study)
                ->where('cur.semester_of_study', $semester_of_study)
                ->whereNull('cur.deleted_at')
                ->where('mps.yearOfStudy',    $year_of_study)
                ->where('mps.semesterOfStudy', $semester_of_study)
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                      ->from('tblexam_marks as prev')
                      ->whereColumn('prev.student_number', 'cur.student_number')
                      ->whereColumn('prev.module_code',    'cur.module_code')
                      ->whereColumn('prev.programme_code', 'cur.programme_code')
                      ->whereColumn('prev.exam_session_id','<', 'cur.exam_session_id')
                      ->where('prev.overall_mark', '<', 50)
                      ->whereNull('prev.deleted_at');
                })
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                      ->from('tblexam_marks as pass')
                      ->whereColumn('pass.student_number', 'cur.student_number')
                      ->whereColumn('pass.module_code',    'cur.module_code')
                      ->whereColumn('pass.programme_code', 'cur.programme_code')
                      ->whereColumn('pass.exam_session_id','<', 'cur.exam_session_id')
                      ->where('pass.overall_mark', '>=', 50)
                      ->whereNull('pass.deleted_at');
                })
                ->distinct()
                ->pluck('cur.student_number'));

            $withdrawing = StudentPartRemark::whereIn('student_number', $total_students)
                ->where(['year_of_study'=>$year_of_study,'semester_of_study'=>$semester_of_study])
                ->where('remark','like','%'. 'WITHDRAW'. '%')
                ->get();

            $discontinue = StudentPartRemark::whereIn('student_number', $total_students)
                ->where(['year_of_study'=>$year_of_study,'semester_of_study'=>$semester_of_study])
                ->where('remark','like','%'. 'DISCONTINUE'. '%')
                ->get();

            $retaking = StudentPartRemark::whereIn('student_number', $total_students)
                ->where(['year_of_study'=>$year_of_study,'semester_of_study'=>$semester_of_study])
                ->where('remark','like','%'. 'RETAKE'. '%')
                ->get();

            $failing = StudentPartRemark::whereIn('student_number', $total_students)
                ->where(['year_of_study'=>$year_of_study,'semester_of_study'=>$semester_of_study])
                ->where('remark','like','%'. 'FAIL'. '%')
                ->get();
            // Signatories section
                $session = ExamSession::has('academic_session')->where('id','=',$exam_session_id)->first();
//                if($session->academic_session)
            return View::make('exams_management.chairperson.results_presentation_web',
                        [   'htmlTable' => $htmlTable,
                            'programme_name'=>$programme_name,
                            'year_of_study'=>$year_of_study,
                            'semester_of_study'=>$semester_of_study,
                            'total_students'=>$total_students,
                            'passing' => $passing,'carrying'=> $carrying ,'repeating'=>$repeating,
                            'withdrawing' =>$withdrawing,'discontinue'=>$discontinue,'retaking'=>$retaking,
                            'failing'=>$failing ,'programme_code' => $programme_code,
                            'exam_session_id' =>  $exam_session_id,
                            'semester_one_profile' => $semester_one_profile_1,
                            'session' => $session,
                            'carry_over_modules' => $carry_over_modules,
                            'exam_marks' => $exam_marks,
                            'passes' => $passes,
                            'fails' => $fails,
                            'totalStudents'=>$totalStudents,
                            'exam_type' =>$exam_type,
                            'state' =>$state,
                            'academic' =>$academic
                        ]);
    }


    public function generate_pdf(Request $request, $programme_code, $year_of_study,$semester_of_study,$exam_session_id,$exam_type, $state){
        $programme_code = $this->decryptRouteValue($programme_code);
        $year_of_study = $this->decryptRouteValue($year_of_study);
        $semester_of_study = $this->decryptRouteValue($semester_of_study);
        $exam_session_id = $this->decryptRouteValue($exam_session_id);
        $exam_type = isset($exam_type) ? $this->decryptRouteValue($exam_type) : null;
        $state = $this->decryptRouteValue($state);
         $academic = 0;



        try {
            $programme_name = ProgrammeDefinition::where('status','active')->where('programmeCode', $programme_code)->first()->programmeName;
            $heading = '<h4>'. $programme_name . ':   Part ' .  $year_of_study.'.'.$semester_of_study  . ' </h4><span class="note">(Subject to ratification by the Senate)</span>' ;
            $dateGenerated = Carbon::now()->format('d-m-Y'); // Get the current date
            $dateText = '<p style="font-size: 10px;">Date Generated: ' . $dateGenerated . '</p>';

            // if($semester_of_study == 2){
            //     $semester_one_profile =  $this->generate_html_view($programme_code, $year_of_study,1,$exam_session_id,$exam_type, $state, $academic);
            // }
            $htmlTable = $this->generate_html_view($programme_code, $year_of_study, $semester_of_study, $exam_session_id,$exam_type, $state, $academic,0);

            $carry_over_modules = '';//$this->carry_over_modules($exam_session_id,$programme_code,$year_of_study,$semester_of_study);
            $signatories = '
            <p>DEAN________________________________________________ DATE________________________________________________</p>
            <p>DEPUTY REGISTRAR___________________________________  DATE________________________________________________</p>

        ';
        if($state < 3){
             $signatories = '
            <p>DEAN________________________________________________ DATE________________________________________________</p>
        ';
        }
            //$semester_one_hd = '<p> Semester 1 Exam Results </p>';

            $semester_two_hd = '<p> Semester 2 Exam Results </p>';

            $carry_over_hd = '<p> Carry Over Modules </p>';

            // Set CSS styles for the table
            $cssStyles = '
            <style>
                table {
                    width: 100%;
                    border-collapse: collapse;

                }

                .exam-center{
                align-items: center;
                }

                /* Adjust font size based on content */
               table th, table td {
                    font-size: 8px;
                }

                h4 {
                    display: inline;
                    margin: 0;
                }
                .note {
                    font-size: 0.8em; /* Adjusts the size of the note text */
                    color: gray; /* Changes the color of the note for distinction */
                }
            </style>
        ';
   /**         if($semester_of_study == 2){
                $htmlContent = $cssStyles . $dateText . $heading . $semester_one_hd . $semester_one_profile  . $semester_two_hd . $htmlTable . $carry_over_hd . $carry_over_modules  .$signatories;
            }else{ **/
           //$htmlContent = $cssStyles . $dateText . $heading . $htmlTable . $carry_over_hd .$carry_over_modules . $signatories;
        $htmlContent = $cssStyles . $dateText . $heading . $htmlTable . $signatories;
           // }


            //$semester_one_profile_1 = $cssStyles . $dateText . $heading . $semester_one_profile;
            if($request->is('faculty-management/generate-excel/*')){
                $spreadsheet = new Spreadsheet();
                $worksheet = $spreadsheet->getActiveSheet();

                $dom = new DOMDocument();
                $dom->loadHTML($htmlContent);
                $tableRows = $dom->getElementsByTagName('tr');

                $rowIndex = 4;

// Process table rows
                foreach ($tableRows as $tableRow) {
                    $tableCells = $tableRow->getElementsByTagName('td');
                    $tableHeaders = $tableRow->getElementsByTagName('th');

                    // Process table headers
                    if ($tableHeaders->length > 0) {
                        $columnIndex = 1;
                        foreach ($tableHeaders as $tableHeader) {
                            $worksheet->setCellValueByColumnAndRow($columnIndex, $rowIndex, $tableHeader->nodeValue);

                            // Set border style for the cell
                            $cellStyle = $worksheet->getStyleByColumnAndRow($columnIndex, $rowIndex);
                            $cellStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                            $cellStyle->getBorders()->getAllBorders()->getColor()->setARGB('FF000000');

                            $columnIndex++;
                        }
                        $rowIndex++;
                    }

                    // Process table cells
                    $columnIndex = 1;
                    foreach ($tableCells as $tableCell) {
                        $worksheet->setCellValueByColumnAndRow($columnIndex, $rowIndex, $tableCell->nodeValue);

                        // Set border style for the cell
                        $cellStyle = $worksheet->getStyleByColumnAndRow($columnIndex, $rowIndex);
                        $cellStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                        $cellStyle->getBorders()->getAllBorders()->getColor()->setARGB('FF000000');

                        $columnIndex++;
                    }

                    $rowIndex++;
                }

// Set the heading and signatories in separate cells
                $headingRowIndex = 1;
                $headingColumnIndex = 1;
                $headingCell = $worksheet->getCellByColumnAndRow($headingColumnIndex, $headingRowIndex);
                $headingCell->setValue($programme_name);
                $headingCell->getStyle()->getFont()->setSize(14)->setBold(true);
                $headingCell->getStyle()->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                $signatoriesRowIndex = $rowIndex + 2;
                $signatoriesColumnIndex = 1;
                $signatoriesCell = $worksheet->getCellByColumnAndRow($signatoriesColumnIndex, $signatoriesRowIndex);
                $signatoriesCell->setValue($signatories);
                $signatoriesCell->getStyle()->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

// Set border style for the heading and signatories cells
                $headingStyle = $worksheet->getStyleByColumnAndRow($headingColumnIndex, $headingRowIndex);
                $headingStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $headingStyle->getBorders()->getAllBorders()->getColor()->setARGB('FF000000');

                $signatoriesStyle = $worksheet->getStyleByColumnAndRow($signatoriesColumnIndex, $signatoriesRowIndex);
                $signatoriesStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $signatoriesStyle->getBorders()->getAllBorders()->getColor()->setARGB('FF000000');

// Create a new Excel writer
                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

// Set the filename
                $filename = $programme_code . '_' . 'Part_' . $year_of_study . '_Semester_' . $semester_of_study . '.xlsx';

// Save the spreadsheet to a file
                $writer->save($filename);

// Set headers for download
                $headers = [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                ];

// Create a StreamedResponse with a callback to stream the file
                $response = new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($filename) {
                    $stream = fopen($filename, 'rb');

                    // Output the file content to the response stream
                    while (!feof($stream)) {
                        echo fread($stream, 4096);
                        flush();
                    }

                    fclose($stream);

                    // Delete the file after streaming
                    unlink($filename);
                }, 200, $headers);

                return $response;

            } elseif ($request->is('faculty-management/generate-pdf/*')){
                $dompdf = new Dompdf();
                $dompdf->loadHtml($htmlContent);
                $filename = $programme_code . '_'.'Part_'. $year_of_study . '_Semester_'. $semester_of_study ;
                // (Optional) Set the paper size and orientation
                $dompdf->setPaper('A4', 'landscape');

                // Render the HTML as PDF
                $dompdf->render();

                // Output the generated PDF as a file for download
                $dompdf->stream($filename.'.pdf');
                //return response($dompdf->output())
                //->header('Content-Type', 'application/pdf')
                //->header('Content-Disposition', 'attachment; filename="'.$filename.'.pdf"');
            }



        } catch (\Throwable $exception) {
            ErrorLogEvent::dispatch($exception);
            return redirect()->back()->with('toast_error', 'Unable to generate the PDF. Please try again.');
        }
    }

    public function results_summary_remark(Request $request){
            $validated = $request->validate([
                'programme_code' => ['required', 'string'],
                'year_of_study' => ['required', 'integer', 'min:1'],
                'semester_of_study' => ['required', 'integer', 'between:1,2'],
                'session' => ['required', 'string', 'max:100'],
                'remark' => ['required', 'string', 'max:255'],
                'state' => ['required', 'integer'],
            ]);

            $programme_code = $this->decryptRouteValue($validated['programme_code']);
            $year_of_study = $validated['year_of_study'];
            $semester_of_study = $validated['semester_of_study'];
            $session = $validated['session'];
            $remark = $validated['remark'];
            $state = $validated['state'];
            $academic = 1;
        try {
            $student_numbers = $this->results_summary_drill_down($year_of_study,$semester_of_study,$session,$programme_code,$remark);
            $exam_session_id = U1AcademicSession::has('exam_session')->where('academic_session_name','=', $session)->first();
            $students = DB::table('tblexam_marks')
                ->leftJoin('tbl_student_part_aggregate', 'tblexam_marks.student_number', '=', 'tbl_student_part_aggregate.student_number')
                ->leftJoin('tbl_student_part_remark', function ($join) use ($year_of_study, $semester_of_study) {
                    $join->on('tblexam_marks.student_number', '=', 'tbl_student_part_remark.student_number')
                        ->where('tbl_student_part_remark.year_of_study', '=', $year_of_study)
                        ->where('tbl_student_part_remark.semester_of_study', '=', $semester_of_study);
                })
                ->leftJoin('studentmember', 'tblexam_marks.student_number', '=', 'studentNumber')
                ->select(
                    'tblexam_marks.student_number',
                    'tblexam_marks.programme_code',
                    'tblexam_marks.module_code',
                    'tblexam_marks.coursework_mark',
                    'tblexam_marks.exam_mark',
                    'tblexam_marks.overall_mark',
                    'tblexam_marks.grade',
                    'tblexam_marks.publish_status',
                    DB::raw('COALESCE(tbl_student_part_aggregate.p1_avg, null) AS p1_avg'),
                    DB::raw('COALESCE(tbl_student_part_aggregate.p2_avg, null) AS p2_avg'),
                    DB::raw('COALESCE(tbl_student_part_aggregate.p3_avg, null) AS p3_avg'),
                    DB::raw('COALESCE(tbl_student_part_aggregate.p4_avg, null) AS p4_avg'),
                    DB::raw('COALESCE(tbl_student_part_aggregate.p5_avg, null) AS p5_avg'),
                    'tbl_student_part_aggregate.overall_avg',
                    'tbl_student_part_aggregate.degree_class',
                    'tbl_student_part_remark.remark',
                    'firstName',
                    'lastName',
                    'tblexam_marks.year_of_study'
                )->whereIn('tblexam_marks.student_number', $student_numbers)
                ->where([
                    'tblexam_marks.programme_code' => $programme_code,
                    'tblexam_marks.year_of_study' => $year_of_study,
                    'tblexam_marks.semester_of_study' => $semester_of_study,
                    'exam_session_id' => $exam_session_id->exam_session->id
                ])
                ->orderBy('lastName')
                ->orderBy('firstName')
                ->get();

            if($students->isEmpty()){
                return redirect()->back()->with('toast_error','No results for the specified remark');
            }

            $htmlTable = '<table style="border-collapse: collapse;" >';
            $htmlTable .= '<thead>';

            $partAggregateColumn = 'Part ' . $students[0]->year_of_study . ' Aggregate';
            $htmlTable .= '<tr style="border: 1px solid black;">';
            $htmlTable .= '<th style="border: 1px solid black; width: 2px;">No.</th>';
            $htmlTable .= '<th>Surname </th>';
            $htmlTable .= '<th>Firstname </th>';
            $htmlTable .= '<th>Reg No</th>';

            $moduleCodes = $students->sortBy('module_code')->sortByDesc('publish_status')->pluck('module_code')->unique();
            foreach ($moduleCodes as $moduleCode) {
                $htmlTable .= '<th style="border: 1px solid black;">' . $moduleCode . ' CW</th>';
                $htmlTable .= '<th style="border: 1px solid black;">' . $moduleCode . ' EX</th>';
                $htmlTable .= '<th style="border: 1px solid black;">' . $moduleCode . ' OM</th>';
            }
            $htmlTable .= '<th>' . $partAggregateColumn . '</th>';
            $htmlTable .= '<th>Overall Aggregate</th>';
            $htmlTable .= '<th>Degree Class</th>';
            $htmlTable .= '<th>Remarks</th>';

            $htmlTable .= '</tr>';
            $htmlTable .= '</thead>';
            $htmlTable .= '<tbody>';

            $processedStudents = [];
            $count = 1;
            foreach ($students as $student) {


                // Check if student has already been processed
                if (in_array($student->student_number, $processedStudents)) {
                    continue; // Skip to next iteration if duplicate
                }

                $htmlTable .= '<tr style="border: 1px solid black;">';
                $htmlTable .= '<td style="border: 1px solid black;width: 2px">' .  $count++ . '</td>';
                $htmlTable .= '<td style="border: 1px solid black;text-transform: uppercase;">' . $student->lastName . '</td>';
                $htmlTable .= '<td style="border: 1px solid black;text-transform: uppercase;">' . $student->firstName.  '</td>';
                $htmlTable .= '<td style="border: 1px solid black;text-transform: uppercase;">' .$student->student_number . '</td>';
                foreach ($moduleCodes as $moduleCode) {
                    $moduleMark = $students
                        ->where('student_number', $student->student_number)
                        ->where('module_code', $moduleCode)
                        ->first();

                   // $htmlTable .= '<td class="exam-center" style="text-align: center">';
                     if ($moduleMark) {
                        $ca =round($moduleMark->coursework_mark )?? '-';
                        $ex = round($moduleMark->exam_mark) ?? '-';
                        $om = $moduleMark->overall_mark ?? '0';

                        $td = '<td style="text-align: center">';
                        $tdOV = '<td style="border-right: 1px solid black; text-align: center">';
                        if ($moduleMark->overall_mark < 50) {
                                $td = '<td style="text-align: center; color:red">';
                                $tdOV = '<td style="border-right: 1px solid black; text-align: center; color:red">';
                            }
                        if ($moduleMark->publish_status) {
                            $td = '<td style="text-align: center; background-color: #dedede">';
                            $tdOV = '<td style="border-right: 1px solid black; text-align: center; background-color: #dedede">';
                            if ($moduleMark->overall_mark < 50) {
                                $td = '<td style="text-align: center; background-color: #dedede; color:red">';
                                $tdOV = '<td style="border-right: 1px solid black; text-align: center; background-color: #dedede; color:red">';
                            }
                        }
                       // $htmlTable .= '<td style="text-align: center; background-color: #dedede">';
                        $htmlTable .= $td . $ca. '</td>';
                        $htmlTable .=  $td . $ex . '</td>';
                        $htmlTable .=  $tdOV . $om . '</td>';

                    } else {
                        // No matching record found, display 'N/A'
                        $htmlTable .= '<td style="text-align: center">-' .'</td>';
                         $htmlTable .= '<td style="text-align: center">-' .'</td>';
                        $htmlTable .= '<td style="border-right: 1px solid black; text-align: center">-' . '</td>';
                    }
                }

                $partAggregateValue = $student->{'p' . $student->year_of_study . '_avg'};

                $htmlTable .= '<td>' . $partAggregateValue . '</td>';
                $htmlTable .= '<td>' . $student->overall_avg . '</td>';
                $htmlTable .= '<td>' . $student->degree_class . '</td>';
                $htmlTable .= '<td  class="uppercase">' . $student->remark . '</td>';

                $htmlTable .= '</tr>';

                // Add the student to the processed list
                $processedStudents[] = $student->student_number;
            }

            $htmlTable .= '</tbody>';
            $htmlTable .= '</table>';
            $programme_name = ProgrammeDefinition::where('status','active')->where('programmeCode', $programme_code)->first()->programmeName;
            return View::make('exams_management.chairperson.results_presentation_drill_down',
                [   'htmlTable' => $htmlTable,
                    'programme_name'=>$programme_name,
                    'year_of_study'=>$year_of_study,
                    'semester_of_study'=>$semester_of_study,
                    'programme_code' => $programme_code,
                    'remark' =>$remark,
                    'state' =>$state,
                    'academic' =>$academic
                ]);

        } catch (\Throwable $exception) {
            ErrorLogEvent::dispatch($exception);
            return redirect()->back()->with('toast_error', 'Unable to load the results summary. Please try again.');
        }
    }
    private function carry_over_students($exam_session_id, $programme_code, $year_of_study, $semester_of_study)
    {
        $student_numbers = ExamCandidate::where(['exam_sess_id'=>$exam_session_id])
                ->whereHas('programme_status',function($query) use($programme_code,$year_of_study,$semester_of_study){
                    $query->where(['programmeCode'=>$programme_code, 'yearOfStudy'=>$year_of_study,'semesterOfStudy'=>$semester_of_study]);
                })->pluck('stud_code');
          $carry_over_students = ExamMark::where([
                                'programme_code' => $programme_code,
                                'seating_id' => 2,
                                'exam_session_id' => $exam_session_id
                            ])
                            ->whereIn('student_number', $student_numbers)
                            ->join('studentmember', 'tblexam_marks.student_number', '=', 'studentmember.studentNumber') // Adjust the table name if necessary
                            ->orderBy('studentmember.lastName') // Use the correct table name
                            ->orderBy('studentmember.firstName') // Use the correct table name
                            ->select('tblexam_marks.*') // Select fields from the ExamMark model
                            ->get();
        return $carry_over_students;
    }

    private function carry_over_modules($exam_session_id, $programme_code, $year_of_study, $semester_of_study){
        //try {
        $carry_over_students = $this->carry_over_students($exam_session_id, $programme_code, $year_of_study, $semester_of_study);
          //   $are_there_any = false;
          //   $student_numbers = ExamCandidate::where(['exam_sess_id'=>$exam_session_id])
          //       ->whereHas('programme_status',function($query) use($programme_code,$year_of_study,$semester_of_study){
          //           $query->where(['programmeCode'=>$programme_code, 'yearOfStudy'=>$year_of_study,'semesterOfStudy'=>$semester_of_study]);
          //       })->pluck('stud_code');
          // $carry_over_students = ExamMark::where([
          //                       'programme_code' => $programme_code,
          //                       'seating_id' => 2,
          //                       'exam_session_id' => $exam_session_id
          //                   ])
          //                   ->whereIn('student_number', $student_numbers)
          //                   ->join('studentmember', 'tblexam_marks.student_number', '=', 'studentmember.studentNumber') // Adjust the table name if necessary
          //                   ->orderBy('studentmember.lastName') // Use the correct table name
          //                   ->orderBy('studentmember.firstName') // Use the correct table name
          //                   ->select('tblexam_marks.*') // Select fields from the ExamMark model
          //                   ->get();

          // $carry_over_students =  DB::table('tblexam_marks')
          //       ->leftJoin('studentmember', 'tblexam_marks.student_number', '=', 'studentNumber')
          //       ->select(
          //           'tblexam_marks.student_number',
          //           'tblexam_marks.programme_code',
          //           'tblexam_marks.module_code',
          //           'tblexam_marks.coursework_mark',
          //           'tblexam_marks.exam_mark',
          //           'tblexam_marks.overall_mark',
          //           'tblexam_marks.grade',
          //           'firstName',
          //           'lastName',
          //           'tblexam_marks.year_of_study',
          //           'tblexam_marks.publish_status',
          //       )
          //       ->where([
          //           'tblexam_marks.programme_code' => $programme_code,
          //           'seating_id' => 2,
          //           'exam_session_id' => $exam_session_id
          //       ])->whereIn('student_number', $student_numbers)
          //       ->orderBy('lastName')
          //       ->orderBy('firstName')
          //       ->get();


            $htmlTable = '<table style="border-collapse: collapse;" >';
            $htmlTable .= '<thead>';

            $partAggregateColumn = 'Part ' . $year_of_study. ' Aggregate';
            $htmlTable .= '<tr style="border: 1px solid black;">';
            $htmlTable .= '<th style="border: 1px solid black; width: 2px;">No.</th>';
            $htmlTable .= '<th>Surname </th>';
            $htmlTable .= '<th>Firstname </th>';
            $htmlTable .= '<th>Reg No</th>';

            $moduleCodes =  $carry_over_students->sortBy('module_code')->sortByDesc('publish_status')->pluck('module_code')->unique();
            foreach ($moduleCodes as $moduleCode) {
                $htmlTable .= '<th style="border: 1px solid black;">' . $moduleCode . ' CW</th>';
                $htmlTable .= '<th style="border: 1px solid black;">' . $moduleCode . ' EX</th>';
                $htmlTable .= '<th style="border: 1px solid black;">' . $moduleCode . ' OM</th>';
            }
            $htmlTable .= '</tr>';
            $htmlTable .= '</thead>';
            $htmlTable .= '<tbody>';

            $processedStudents = [];
            $count = 1;
            foreach ( $carry_over_students as $student) {


                // Check if student has already been processed
                if (in_array($student->student_number, $processedStudents)) {
                    continue; // Skip to next iteration if duplicate
                }

                $htmlTable .= '<tr style="border: 1px solid black;">';
                $htmlTable .= '<td style="border: 1px solid black; width: 2px">' .  $count++ . '</td>';
                $htmlTable .= '<td style="border: 1px solid black;text-transform: uppercase;">' . $student->lastName . '</td>';
                $htmlTable .= '<td style="border: 1px solid black;text-transform: uppercase;">' . $student->firstName.  '</td>';
                $htmlTable .= '<td style="border: 1px solid black;text-transform: uppercase;">' .$student->student_number . '</td>';
                foreach ($moduleCodes as $moduleCode) {
                    $moduleMark =  $carry_over_students
                        ->where('student_number', $student->student_number)
                        ->where('module_code', $moduleCode)
                        ->first();

                      if ($moduleMark) {
                        $ca =round($moduleMark->coursework_mark )?? '-';
                        $ex = round($moduleMark->exam_mark) ?? '-';
                        $om = $moduleMark->overall_mark ?? '0';

                        $td = '<td style="text-align: center">';
                        $tdOV = '<td style="border-right: 1px solid black; text-align: center">';
                        if ($moduleMark->overall_mark < 50) {
                                $td = '<td style="text-align: center; color:red">';
                                $tdOV = '<td style="border-right: 1px solid black; text-align: center; color:red">';
                            }
                        if ($moduleMark->publish_status) {
                            $td = '<td style="text-align: center; background-color: #dedede">';
                            $tdOV = '<td style="border-right: 1px solid black; text-align: center; background-color: #dedede">';
                            if ($moduleMark->overall_mark < 50) {
                                $td = '<td style="text-align: center; background-color: #dedede; color:red">';
                                $tdOV = '<td style="border-right: 1px solid black; text-align: center; background-color: #dedede; color:red">';
                            }
                        }
                       // $htmlTable .= '<td style="text-align: center; background-color: #dedede">';
                        $htmlTable .= $td . $ca. '</td>';
                        $htmlTable .=  $td . $ex . '</td>';
                        $htmlTable .=  $tdOV . $om . '</td>';

                    } else {
                        // No matching record found, display 'N/A'
                        $htmlTable .= '<td style="text-align: center">-' .'</td>';
                         $htmlTable .= '<td style="text-align: center">-' .'</td>';
                        $htmlTable .= '<td style="border-right: 1px solid black; text-align: center">-' . '</td>';
                    }

                }

                $htmlTable .= '</tr>';
                // Add the student to the processed list
                $processedStudents[] = $student->student_number;
            }

            $htmlTable .= '</tbody>';
            $htmlTable .= '</table>';

            return $htmlTable;


    }
    /**
     * @throws \Exception
     */
    private function results_summary_drill_down($year_of_study, $semester_of_study, $session, $programme_code, $remark=null){
        try {
            if ($remark == 'PASS'){
                return StudentPartRemark::where(['year_of_study'=>$year_of_study, 'semester_of_study'=>$semester_of_study,
                    'session'=>$session,'programme_code'=>$programme_code])
                    ->where(function ($query) use($remark){
                        $query->where('remark','like','%'. $remark . '%')
                            ->orWhere('remark','like','%'. 'PENDING'. '%')
                            ->orWhere('remark','=', 'PROCEED');
                    })
                    ->pluck('student_number');
            }else{
                return StudentPartRemark::where(['year_of_study'=>$year_of_study, 'semester_of_study'=>$semester_of_study,
                    'session'=>$session,'programme_code'=>$programme_code])->where('remark','LIKE', '%'. $remark . '%')->pluck('student_number');
            }

        } catch (\Throwable $exception) {
            ErrorLogEvent::dispatch($exception);
            throw $exception;
        }
    }
}
