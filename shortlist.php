<?php

namespace App\Http\Controllers\Admin;

use App\ApplicationStatus;
use App\Helper\Reply;
use App\Http\Requests\InterviewSchedule\StoreRequest;
use App\Http\Requests\StoreJobApplication;
use App\Http\Requests\UpdateJobApplication;
use App\InterviewSchedule;
use App\InterviewScheduleEmployee;
use App\Job;
use App\JobApplication;
use App\JobApplicationAnswer;
use App\JobLocation;
use App\JobQuestion;
use App\Notifications\CandidateScheduleInterview;
use App\Notifications\ScheduleInterview;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Response;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;
use App\CandidateInfo;
use App\Company;
use App\CandidateWorkHistory;
use App\CandidateEducation;
use App\CandidateOlevel;
use App\CandidateOlevelResult;
use Illuminate\Support\Facades\Schema;
use App\JobApplicationTestGroup;
use App\CandidateCompetencyExercise;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

class AdminJobApplicationController extends AdminBaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = __('menu.jobApplications');
        $this->pageIcon = 'icon-user';
        $this->jobApplicationData = array();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        abort_if(!$this->user->can('view_job_applications'), 403);

        $this->boardColumns = ApplicationStatus::with(['applications', 'applications.schedule'])->get();
        $boardStracture = [];
        foreach ($this->boardColumns as $key => $column) {
            $boardStracture[$column->id] = [ ];
            foreach ($column->applications as $application) {
                $boardStracture[$column->id][] = $application->id;
            }
        }
        $this->boardStracture = json_encode($boardStracture);
        $this->currentDate = Carbon::now()->timestamp;

        return view('admin.job-applications.board', $this->data);
    }

    public function singleCompany($id)
    {
        abort_if(!$this->user->can('view_job_applications'), 403);

        if ($id) {
            $this->boardColumns = ApplicationStatus::all();
            $this->locations = JobLocation::all();
            $this->jobs = Job::all();
            $this->singleEntityId = $id;
            $this->singleEntityIdType = 'company';
            return view('admin.job-applications.index', $this->data);
        }
    }

    public function singleJob(Request $request, $id)
    {
        abort_if(!$this->user->can('view_job_applications'), 403);
        if ($id) {
            $this->boardColumns = ApplicationStatus
                ::where("status", "!=", "phone screen")->get();

            $this->locations = JobLocation::all();
            $this->jobs = Job::all();
            $this->singleEntityId = $id;
            $this->singleEntityIdType = 'job';
            $this->jobById = Job::find($id);
            if ($request->type == 'ajax') {
                return Reply::dataOnly(['status' => 'success', 'data' => json_encode($this->jobById)], 200);
            }
            return view('admin.job-applications.index', $this->data);
        }
    }

    public function create()
    {
        abort_if(!$this->user->can('add_job_applications'), 403);
        $jobs = Job::activeJobs();
        $this->jobs = $jobs;
        $this->jobsPagination = $jobs->simplePaginate(10);
        return view('admin.job-applications.create', $this->data);
    }

    /**
     * @param $jobID
     * @return mixed
     * @throws \Throwable
     */
    public function jobQuestion($jobID)
    {
        $this->jobQuestion = JobQuestion::with(['question'])->where('job_id', $jobID)->get();
        $view = view('admin.job-applications.job-question', $this->data)->render();
        $count = count($this->jobQuestion);

        return Reply::dataOnly(['status' => 'success', 'view' => $view, 'count' => $count]);
    }


    public function edit($id)
    {
        abort_if(!$this->user->can('edit_job_applications'), 403);

        $this->statuses = ApplicationStatus::all();
        $this->application = JobApplication::find($id);
        $this->jobQuestion = JobQuestion::with(['question'])
            ->where('job_id', $this->application->job_id)->get();

        return view('admin.job-applications.edit', $this->data);
    }

    public function data(Request $request)
    {

        abort_if(!$this->user->can('view_job_applications'), 403);

        $jobApplications = $this->getShortlistData($request);
        $this->jobApplicationData = $jobApplications;
        return DataTables::of($jobApplications)
            ->addColumn('action', function ($row) {
                $action = '';
                if ($this->user->can('edit_job_applications')) {
                    $action .= '<a href="' . route('admin.job-applications.edit', [$row->id]) . '" class="btn btn-primary btn-circle"
                      data-toggle="tooltip" data-original-title="' . __('app.edit') . '"><i class="fa fa-pencil" aria-hidden="true"></i></a>';
                }

                if ($this->user->can('delete_job_applications')) {
                    $action .= ' <a href="javascript:;" class="btn btn-danger btn-circle sa-params"
                      data-toggle="tooltip" data-row-id="' . $row->id . '" data-original-title="' . __('app.delete') . '"><i class="fa fa-times" aria-hidden="true"></i></a>';
                }
                return $action;
            })
            ->editColumn('select_user', function ($row) {
                $test_taker_uri = 'noUri';
                if (!empty($row->test_groups)) {
                    $test_group = $row->test_groups->first();
                    $test_taker_uri = $test_group['test_taker_uri'] ? $test_group['test_taker_uri'] : 'noUri';
                }
                return '<input type="checkbox" class="cd-radio-input" id="' . $row->id . '" name="candidate_selected[]" value= "' . $row->id . '|' . $row->email . '|' . $test_taker_uri . '|' . $row->full_name . '"  </input>';
            })
            ->editColumn('full_name', function ($row) {
                return '<a href="javascript:;" class="show-detail" data-widget="control-sidebar" data-slide="true" data-row-id="' . $row->id . '">' . ucwords($row->full_name) . '</a>';
            })
            ->editColumn('email', function ($row) {
                $email = $row->email ? $row->email : '- -';
                return ucfirst($email);
            })
            ->editColumn('resume', function ($row) {
                return '<a href="' . asset($row->resume) . '" target="_blank">' . __('app.view') . ' ' . __('modules.jobApplication.resume') . '</a>';
            })
            ->editColumn('phone', function ($row) {
                $phone = $row->phone ? $row->phone : '- -';
                return ucwords($phone);
            })
            ->editColumn('status', function ($row) {
                $status = is_object($row->status) ? $row->status->status : $row->status;
                return ucwords($status);
            })
            ->rawColumns(['action', 'select_user', 'resume', 'full_name'])
            ->addIndexColumn()
            ->make(true);

    }

    private function getShortlistData($request)
    {
        // optimizing the search functionality for robustness and speed
        // implementing Laravel's inbuilt ORM/Eloquent functionalites and raw queries to handle large search criteria
        $jobApplications = JobApplication::select('job_applications.id', 'job_applications.full_name', 'job_applications.resume', 'job_applications.phone',
            'job_applications.email', 'job_applications.candidate_id', 'jobs.title', 'job_locations.location', 'application_status.status',
            'job_applications.created_at', 'job_applications.job_id', 'job_applications.job_role',
            'candidate_data.gender',
            'candidate_data.date_of_birth', 'candidate_data.state', 'candidate_data.residence_state',
            'candidate_data.lga', 'candidate_data.certifications',
            'candidate_data.skills', 'candidate_data.cv_url', 'candidate_data.experience_level',
            'candidate_data.nysc_status', 'candidate_data.nysc_completion_year', 'candidate_data.work_history as work',
            'candidate_data.olevel', 'candidate_data.education', 'candidate_data.olevel_scores'
        )
            ->with(['status', 'olevel', 'test_groups'])
            ->join('jobs', 'jobs.id', 'job_applications.job_id')
            ->join('candidate_data', 'candidate_data.candidate_id', 'job_applications.candidate_id')
            ->leftjoin('job_locations', 'job_locations.id', 'jobs.location_id')
            ->leftjoin('application_status', 'application_status.id', 'job_applications.status_id')
            ->whereNotNull('job_applications.status_id');

        // Filter by company_id
        if ($request->singleEntityId != 'all' && $request->singleEntityId != '' && $request->singleEntityIdType == 'company') {
            $jobApplications = $jobApplications->where('jobs.company_id', $request->singleEntityId);
        }
        // Filter by job_id
        if ($request->singleEntityId != 'all' && $request->singleEntityId != '' && $request->singleEntityIdType == 'job') {
            $jobApplications = $jobApplications->where('job_applications.job_id', $request->singleEntityId);
        }

        // Filter by status
        if ($request->status != 'all' && $request->status != '') {
            $jobApplications = $jobApplications->where('job_applications.status_id', $request->status);
        }

        // Filter By jobs
        if ($request->jobs != 'all' && $request->jobs != '') {
            $jobApplications = $jobApplications->where('job_applications.job_id', $request->jobs);
        }

        // Filter By job role
        if ($request->jobRole != 'all' && $request->jobRole != '') {
            $jobApplications = $jobApplications->where('job_applications.job_role', 'like', '%' . $request->jobRole . '%');
        }

        // Filter by location
        if ($request->location != 'all' && $request->location != '') {
            $jobApplications = $jobApplications->where('jobs.location_id', $request->location);
        }

        // Filter by StartDate
        if ($request->startDate != null && $request->startDate != '' && $request->startDate != 0) {
            $jobApplications = $jobApplications->where(DB::raw('DATE(rt_job_applications.`created_at`)'), '>=', "$request->startDate");
        }

        // Filter by EndDate
        if ($request->endDate != null && $request->endDate != '' && $request->endDate != 0) {
            $jobApplications = $jobApplications->where(DB::raw('DATE(rt_job_applications.`created_at`)'), '<=', "$request->endDate");
        }

        if ($request->shortlisting == "shortlisting") {

            //Filter by Residential State
            $candidateResidentialStateQuery = $request->candidateResidentialState; //
            if (!empty($candidateResidentialStateQuery)) {
                $candidateResidentialStateQuery = explode(",", $candidateResidentialStateQuery);
                if ($candidateResidentialStateQuery) {
                    $jobApplications->where(function ($q) use ($candidateResidentialStateQuery) {
                        $q->whereIn('residence_state', $candidateResidentialStateQuery);
                    });
                }
            }

            //Filter by State of Origin
            $candidateStateofOriginQuery = $request->candidate_state_of_origin; //
            if (!empty($candidateStateofOriginQuery)) {
                $candidateStateofOriginQuery = explode(",", $candidateStateofOriginQuery);
                if ($candidateStateofOriginQuery) {
                    $jobApplications->where(function ($q) use ($candidateStateofOriginQuery) {
                        $q->whereIn('state', $candidateStateofOriginQuery);
                        
                    });
                }
            }


            //Qualification Filtering
            $candidateQualificationsQuery = $request->input('candidateQualifications');
            if ($candidateQualificationsQuery != "") {
                $candidateQualificationsQuery = $candidateQualificationsQuery ? explode(",", $candidateQualificationsQuery) : array('');
                $jobApplications->where(function ($q) use ($candidateQualificationsQuery) {
                    foreach ($candidateQualificationsQuery as $qualification) {
                        $q->orWhereRaw(DB::raw("json_contains(`education`, '{\"qualification\" : \"" . $qualification . "\"}')"));

                    }
                });
            }

            //Filter by University
            $universityQuery = $request->input('university');
            if ($universityQuery != "") {
                $universityQuery = $universityQuery ? explode(",", $universityQuery) : array('');
                $jobApplications->where(function ($q) use ($universityQuery) {
                    foreach ($universityQuery as $university) {
                        $q->orWhereRaw(DB::raw("LOWER(education->\"$[*].institution\") like '%" . strtolower($university) . "%'"));

                    }
                });
            }


            //Filter by course of study
            $candidateCourseQuery = $request->candidateCourse;
            $jobId =  $request->singleEntityId;
            if ($candidateCourseQuery != "") {
                $candidateCourseQuery = $candidateCourseQuery ? explode(",", $candidateCourseQuery) : array('');
                $jobApplications->where(function ($q) use ($candidateCourseQuery, $jobId) {
                    foreach ($candidateCourseQuery as $course) {
                        $q->orWhereRaw(DB::raw("LOWER(education->\"$[*].field_of_study\") like '%" . strtolower($course) . "%'"));
                        // $qry = "SELECT count(*) FROM rt_candidate_educations INNER JOIN 
                        // rt_job_applications ON rt_candidate_educations.candidate_id=rt_job_applications.candidate_id
                        //  WHERE rt_job_applications.job_id=".$jobId." AND field_of_study like '%" . $course . "%'";
                        // $q->orWhereRaw(DB::raw($qry));
                       

                    }
                });
            }

            //Filter by degree
            $candidateDegreesQuery = $request->input('candidateDegrees');
            if ($candidateDegreesQuery != "") {
                $candidateDegreesQuery = $candidateDegreesQuery ? explode(",", $candidateDegreesQuery) : array('');
                $jobApplications->where(function ($q) use ($candidateDegreesQuery) {
                    foreach ($candidateDegreesQuery as $degree) {
                        $q->orWhereRaw(DB::raw("json_contains(`education`, '{\"grade\" : \"" . $degree . "\"}')"));

                    }
                });
            }


            $jobTitleQuery = $request->jobTitles;
            $jobTitleQuery = $jobTitleQuery ? explode(",", $jobTitleQuery) : array('');

            $industryQuery = $request->industry;
            $industryQuery = $industryQuery ? explode(",", $industryQuery) : array('');

            $companiesQuery = $request->companies;
            $companiesQuery = $companiesQuery ? explode(",", $companiesQuery) : array('');

            if ($jobTitleQuery[0] != "" || $industryQuery[0] != '' || $companiesQuery[0] != '') {

                //Job title Filtering
                if ($jobTitleQuery != "") {
                    $jobApplications->where(function ($q) use ($jobTitleQuery) {
                        foreach ($jobTitleQuery as $title) {
                            $q->orWhereRaw(DB::raw("LOWER(work_history->\"$[*].title\") like '%" . strtolower($title) . "%'"));

                        }
                    });
                }

                //Company Filtering
                if ($companiesQuery != "") {
                    $jobApplications->where(function ($q) use ($companiesQuery) {
                        foreach ($companiesQuery as $company) {
                            $q->orWhereRaw(DB::raw("LOWER(work_history->\"$[*].company\") like '%" . strtolower($company) . "%'"));

                        }
                    });
                }


                //Industry Filtering
                if ($industryQuery != "") {
                    $jobApplications->where(function ($q) use ($industryQuery) {
                        foreach ($industryQuery as $industry) {
                            $q->orWhereRaw(DB::raw("LOWER(work_history->\"$[*].industry\") like '%" . strtolower($industry). "%'"));

                        }
                    });
                }
            }

            //Filter by experience level
            $candidate_experience_higher_bound = $request->input('candidate_experience_higher_bound') ? $request->input('candidate_experience_higher_bound') : null;
            $candidate_experience_lower_bound = $request->input('candidate_experience_lower_bound') ? $request->input('candidate_experience_lower_bound') : null;
            if ($candidate_experience_lower_bound != null && $candidate_experience_higher_bound != null) {
                $jobApplications->whereBetween('experience_level', [$candidate_experience_lower_bound, $candidate_experience_higher_bound]);
            }

            //Filter By relevant experience
            $relevant_experience_higher_bound = $request->input('relevant_experience_higher_bound') ? $request->input('relevant_experience_higher_bound') : null;
            $relevant_experience_lower_bound = $request->input('relevant_experience_lower_bound') ? $request->input('relevant_experience_lower_bound') : null;
            if ($relevant_experience_lower_bound != null && $relevant_experience_higher_bound != null) {
                $jobApplications->whereBetween('relevant_years_experience', [$relevant_experience_lower_bound, $relevant_experience_higher_bound]);
            }

            //Filter by Age range
            $candidate_age_lower_bound = $request->input('candidate_age_lower_bound') ? $request->input('candidate_age_lower_bound') : null;
            $candidate_age_higher_bound = $request->input('candidate_age_higher_bound') ? $request->input('candidate_age_higher_bound') : null;
            if ($candidate_age_lower_bound != null && $candidate_age_higher_bound != null) {
                $jobApplications->whereBetween(DB::raw('TIMESTAMPDIFF(YEAR,date_of_birth,CURDATE())'), array($candidate_age_lower_bound, $candidate_age_higher_bound));
            }

            //Filter by skills
            $skillsQuery = $request->skills;
            $skillsQuery = $skillsQuery ? explode(",", $skillsQuery) : array('');
            if ($skillsQuery[0] != '') {
                $jobApplications->where(function ($query) use ($skillsQuery) {
                    foreach ($skillsQuery as $skill) {
                        $query->orWhereRaw("LOWER(skills) like '%" . strtolower($skill) . "%'");
                    }
                });
            }

          
            //Filter by certifications
            $candidatCertificationsQuery = $request->candidate_certifications; //
            $candidatCertificationsQuery = $candidatCertificationsQuery ? explode(",", $candidatCertificationsQuery) : array('');
            if ($candidatCertificationsQuery[0] != '') {
                $jobApplications->where(function ($query) use ($candidatCertificationsQuery) {
                    foreach ($candidatCertificationsQuery as $certifications) {
                        $query->orWhere('certifications', 'like', '%' . $certifications . '%');
                    }
                });
            }
         

            //Filter by completed nysc
            $nyscStatusQuery = $request->input("nysc_strict_result");
            if ($nyscStatusQuery == 'Completed') {
                $jobApplications->where('nysc_status', '=', $nyscStatusQuery);
            }

            //Filter by Olevel bound
            $olevel_higher_bound = $request->input('olevel_higher_bound') ? $request->input('olevel_higher_bound') : null;
            $olevel_lower_bound = $request->input('olevel_lower_bound') ? $request->input('olevel_lower_bound') : null;
            if ($olevel_higher_bound != null && $olevel_lower_bound != null) {
                $jobApplications->where(function ($q) use ($olevel_higher_bound, $olevel_lower_bound) {
                    for ($i = 0; $i <= 5; $i++) {
                        $q->orWhere(function ($q) use ($olevel_higher_bound, $olevel_lower_bound, $i) {
                            $q->whereRaw("CAST(JSON_EXTRACT(olevel, \"$[" . $i . "].total\")  as UNSIGNED) >=  " . $olevel_lower_bound);
                            $q->whereRaw("CAST(JSON_EXTRACT(olevel, \"$[" . $i . "].total\")  as UNSIGNED) <=  " . $olevel_higher_bound);
                        });
                    }
                });

            };

        }

      
        $this->getQuery($jobApplications);
        $this->jobApplicationData = $jobApplications;
        return $jobApplications;

    }

    public function createSchedule(Request $request, $id)
    {
        abort_if(!$this->user->can('add_schedule'), 403);
        $this->candidates = JobApplication::all();
        $this->users = User::all();
        $this->scheduleDate = $request->date;
        $this->currentApplicant = JobApplication::findOrFail($id);
        return view('admin.job-applications.interview-create', $this->data)->render();

    }

    public function storeSchedule(StoreRequest $request)
    {
        abort_if(!$this->user->can('add_schedule'), 403);

        $dateTime = $request->scheduleDate . ' ' . $request->scheduleTime;
        $dateTime = Carbon::createFromFormat('Y-m-d H:i', $dateTime);

        // store Schedule
        $interviewSchedule = new InterviewSchedule();
        $interviewSchedule->job_application_id = $request->candidate;
        $interviewSchedule->schedule_date = $dateTime;
        $interviewSchedule->save();

        // Update Schedule Status
        $jobApplication = JobApplication::find($request->candidate);
        $jobApplication->status_id = 3;
        $jobApplication->save();

        if (!empty($request->employee)) {
            InterviewScheduleEmployee::where('interview_schedule_id', $interviewSchedule->id)->delete();
            foreach ($request->employee as $employee) {
                $scheduleEmployee = new InterviewScheduleEmployee();
                $scheduleEmployee->user_id = $employee;
                $scheduleEmployee->interview_schedule_id = $interviewSchedule->id;
                $scheduleEmployee->save();

                $user = User::find($employee);
                // Mail to employee for inform interview schedule
                Notification::send($user, new ScheduleInterview($jobApplication));
            }
        }

        // mail to candidate for inform interview schedule
        // Notification::send($jobApplication, new CandidateScheduleInterview($jobApplication, $interviewSchedule));

        return Reply::redirect(route('admin.interview-schedule.index'), __('menu.interviewSchedule') . ' ' . __('messages.createdSuccessfully'));
    }


    public function store(StoreJobApplication $request)
    {
        abort_if(!$this->user->can('add_job_applications'), 403);

        $jobApplication = new JobApplication();
        $jobApplication->full_name = $request->full_name;
        $jobApplication->job_id = $request->job_id;
        $jobApplication->status_id = 1; //applied status id
        $jobApplication->email = $request->email;
        $jobApplication->phone = $request->phone;
        $jobApplication->cover_letter = $request->cover_letter;
        $jobApplication->column_priority = 0;

        if ($request->hasFile('resume')) {
            $jobApplication->resume = $request->resume->hashName();
            $request->resume->store('user-uploads/resumes');
        }

        if ($request->hasFile('photo')) {
            $jobApplication->photo = $request->photo->hashName();
            $request->photo->store('user-uploads/candidate-photos');
        }
        $jobApplication->save();

        // Job Application Answer save
        if (isset($request->answer) && !empty($request->answer)) {
            JobApplicationAnswer::where('job_application_id', $jobApplication->id)->delete();

            foreach ($request->answer as $key => $value) {
                $answer = new JobApplicationAnswer();
                $answer->job_application_id = $jobApplication->id;
                $answer->job_id = $request->job_id;
                $answer->question_id = $key;
                $answer->answer = $value;
                $answer->save();
            }
        }

        return Reply::redirect(route('admin.job-applications.index'), __('menu.jobApplications') . ' ' . __('messages.createdSuccessfully'));
    }

    private function createTestGroup($jobApplication)
    {
        $testTakerEntryExist = jobApplicationTestGroup::where('job_id', '=', $jobApplication->job_id
        )->where('job_application_id', '=', $jobApplication->id)->first();
        $target_job_application_same = false;
        if ($testTakerEntryExist) {
            $target_job_application_same = ($testTakerEntryExist->job_id == $jobApplication->job_id) ? true : false;
        }

        if (!$target_job_application_same) {
            $testGroup = new jobApplicationTestGroup();
            $testGroup->job_application_id = $jobApplication->id;
            $testGroup->job_id = $jobApplication->job_id;
            $testGroup->save();
        }
    }

    public function updateJobAppStatusById(Request $request, $id)
    {
        abort_if(!$this->user->can('edit_job_applications'), 403);
        $applicationStatus = ApplicationStatus::find($id);
        $jobApplication = [];
        $candidateIds = [];
        $selectedCandidates = $request->selectedCandidates;
        $jobIdArray = $request->jobIdArray;

        if ($applicationStatus->status == 'online test') {

            if ($id && !empty($request->jobIdArray)) {
                if($request->jobIdArray=="all"){
                   foreach( $this->jobApplicationData as $jobApplicationData){
                       $jobApp = JobApplication::where('id', $jobApplicationData->id)
                                                 ->where('job_id',  $jobApplicationData->job_id)
                                                 ->get();
                       $jobApp->status_id = $id;
                       $jobApp->save();
                       self::createTestGroup($jobApp);
                   }
                  
                }else{
                    foreach ($request->jobIdArray as $jobId) {
                        $jobApplication = JobApplication::find($jobId['applicationId']);
                        $jobApplication->status_id = $id;
                        $jobApplication->save();
                        self::createTestGroup($jobApplication);
                    }

                }
               
                return Reply::dataOnly(['status' => 'success', 'message']);
            }


            // $jobApplications = JobApplication::where('job_id', $request->jobId)->get();
            // foreach ($jobApplications as $jobApplication) {
            //     $jobApplication->status_id = $id;
            //     $jobApplication->save();
            //     self::createTestGroup($jobApplication);
            // }
        }

        if ($id && !empty($request->jobIdArray)) {
            
            if($request->jobIdArray=="all"){
                foreach( $this->jobApplicationData as $jobApplicationData){
                    $jobApp = JobApplication::where('id', $jobApplicationData->id)
                    ->where('job_id',  $jobApplicationData->job_id)->get(); 
                    $jobApp->status_id = $id;
                    $jobApp->save();
                    $applications[] = ['applicationId'=>$jobId['applicationId'], 'candidateId'=>$jobApplicationData->candidate_id, 'jobId'=> $jobApplicationData->job_id];
                }
                $request = new Request();
                $request->applications = $applications;
                $request->exercise = 'Exercise 1';
               
            }else{
                $applications = [];
            foreach ($request->jobIdArray as $jobId) {
                $jobApplication = JobApplication::find($jobId['applicationId']);
                $jobApplication->status_id = $id;
                $jobApplication->save();

                $applications[] = ['applicationId'=>$jobId['applicationId'], 'candidateId'=>$jobApplication->candidate_id, 'jobId'=> $jobApplication->job_id];


                // if($id==3){
                //     $candidateCompetencyExercise = new CandidateCompetencyExercise();
                //     $candidateCompetencyExercise->exercise = 'Exercise 1';
                //     $candidateCompetencyExercise->job_application_id = $jobId['applicationId'];
                //     $candidateCompetencyExercise->save();
                // }
               // self::createTestGroup($jobApplication);
            }
            if($id==3){
                $request = new Request();
                $request->applications = $applications;
                $request->exercise = 'Exercise 1';

            $adminCompetenciesController = new AdminCompetenciesController();
            $adminCompetenciesController->moveCandidatesToExercise($request);
            }
        }
            return Reply::dataOnly(['status' => 'success', 'message']);
        } else {
            return Reply::dataOnly(['status' => 'success',]);
        }
    }

    public function update(UpdateJobApplication $request, $id)
    {
        abort_if(!$this->user->can('edit_job_applications'), 403);

        $jobApplication = JobApplication::find($id);
        $jobApplication->full_name = $request->full_name;
        $jobApplication->status_id = $request->status_id;
        $jobApplication->email = $request->email;
        $jobApplication->phone = $request->phone;
        $jobApplication->cover_letter = $request->cover_letter;

        if ($request->hasFile('resume')) {
            $jobApplication->resume = $request->resume->hashName();
            $request->resume->store('user-uploads/resumes');
        }

        if ($request->hasFile('photo')) {
            $jobApplication->photo = $request->photo->hashName();
            $request->photo->store('user-uploads/candidate-photos');
        }

        $jobApplication->save();
        // Job Application Answer save
        if (isset($request->answer) && count($request->answer) > 0) {
            JobApplicationAnswer::where('job_application_id', $jobApplication->id)->delete();
            foreach ($request->answer as $key => $value) {
                $answer = new JobApplicationAnswer();
                $answer->job_application_id = $jobApplication->id;
                $answer->job_id = $jobApplication->job_id;
                $answer->question_id = $key;
                $answer->answer = $value;
                $answer->save();
            }
        }

        return Reply::redirect(route('admin.job-applications.index'), __('menu.jobApplications') . ' ' . __('messages.updatedSuccessfully'));
    }

    public function destroy($id)
    {
        abort_if(!$this->user->can('delete_job_applications'), 403);

        JobApplication::destroy($id);
        return Reply::success(__('messages.recordDeleted'));
    }

    public function show($id)
    {
        $this->application = JobApplication::with(['schedule', 'status', 'schedule.employee', 'schedule.comments.user'])->find($id);
        $this->candidate = CandidateInfo::where('candidate_id', $this->application->candidate_id)->first();
        $this->answers = JobApplicationAnswer::with(['question'])
            ->where('job_id', $this->application->job_id)
            ->where('job_application_id', $this->application->id)
            ->get();

        $view = view('admin.job-applications.show', $this->data)->render();
        return Reply::dataOnly(['status' => 'success', 'view' => $view]);
    }

    public function updateIndex(Request $request)
    {
        $taskIds = $request->applicationIds;
        $boardColumnIds = $request->boardColumnIds;
        $priorities = $request->prioritys;

        if (!is_null($taskIds)) {
            foreach ($taskIds as $key => $taskId) {
                if (!is_null($taskId)) {

                    $task = JobApplication::find($taskId);
                    $task->column_priority = $priorities[$key];
                    $task->status_id = $boardColumnIds[$key];

                    $task->save();
                }
            }
        }

        return Reply::dataOnly(['status' => 'success']);
    }

    public function table()
    {
        abort_if(!$this->user->can('view_job_applications'), 403);

        $this->boardColumns = ApplicationStatus::all();
        $this->locations = JobLocation::all();
        $this->jobs = Job::all();
        $this->singleEntityId = '';
        $this->singleEntityIdType = 'job';

        return view('admin.job-applications.index', $this->data);
    }

    public function ratingSave(Request $request, $id)
    {
        abort_if(!$this->user->can('edit_job_applications'), 403);

        $application = JobApplication::findOrFail($id);
        $application->rating = $request->rating;
        $application->save();

        return Reply::success(__('messages.updatedSuccessfully'));
    }

    // Job Applications data Export
    // function to export large amount of data to excel made efficient by using laravel's data chunking functionality
    public function export(Request $request, $status, $location, $startDate, $endDate, $jobs, $type)
    {
        $request->jobs = $jobs;
        $request->type = $type;
        $request->location = $location;
        $request->status = $status;
        $request->endDate = $endDate;
        $request->startDate = $startDate;
        $request->university = "";
        $request->startDate = $startDate;


        $total_education = CandidateEducation::select(DB::raw('count(*) as total'))->groupBy('candidate_id')->orderBy('total', 'desc')->first()->total;
        $total_work = CandidateWorkHistory::select(DB::raw('count(*) as total'))->groupBy('candidate_id')->orderBy('total', 'desc')->first()->total;
        $total_olevel = CandidateOlevel::select(DB::raw('count(*) as total'))->groupBy('candidate_id')->orderBy('total', 'desc')->first()->total;

        // $jobApplications = json_decode($this->readShortListFile());
        $jobApplicationsQuery = $this->getShortlistData($request);
        $exportArray = [];
        $columWorkName = "";

        if ($type == 'csv') {

            $headers = [
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0'
                , 'Content-type' => 'text/csv'
                , 'Content-Disposition' => 'attachment; filename=Recruit Candidates.csv'
                , 'Expires' => '0'
                , 'Pragma' => 'public'
            ];

            $exportArray = ['S/N', 'Job Title', 'Name', 'Email', 'Mobile', 'Status', 'Registered On', 'Gender', 'DOB', 'State', 'L.G.A', 'Residence State',
                'Certifications', 'Skills', 'Resume', 'Experience Level', 'NYSC_Status', 'NYSC Completion Year'];

            $columWorkName = Schema::getColumnListing('candidate_work_histories');
            for ($i = 0; $i < $total_work; $i++) {
                foreach ($columWorkName as $columWorkNamedata) {
                    $exportArray[] = 'work_' . '' . $i . '_' . $columWorkNamedata;
                }
            }

            $columEduName = Schema::getColumnListing('candidate_educations');
            for ($j = 0; $j < $total_education; $j++) {
                foreach ($columEduName as $columnIndex => $columEduNamedata) {
                    $exportArray[] = 'education_' . '' . $j . '_' . $columEduNamedata;
                }
            }

            for ($j = 0; $j < $total_olevel; $j++) {
                $exportArray[] = 'exam_type_' . $j;
                for ($x = 0; $x < 7; $x++) {
                    $exportArray[] = $j . '_subject_' . $x;
                    $exportArray[] = $j . '_subject_' . $x . '_result';
                }
            }
            $columns = $exportArray;
            $callback = function () use ($jobApplicationsQuery, $columns, $total_work, $total_olevel, $total_education, $columWorkName, $columEduName) {
                $file = fopen('php://output', 'w');

                fputcsv($file, $columns);
                $jobApplicationsQuery->chunk(500, function ($jobApplications) use ($columns, $total_work, $total_olevel, $total_education, $columWorkName, $columEduName, $file) {
                    foreach ($jobApplications as $jobApplication) {

                        $rowdata = array('id' => $jobApplication->id,
                            'title' => $jobApplication->title,
                            'full_name' => $jobApplication->full_name,
                            'email' => $jobApplication->email,
                            'phone' => $jobApplication->phone,
                            'status' => $jobApplication->status,
                            'created_at' => $jobApplication->created_at,
                            'gender' => $jobApplication->gender,
                            'date_of_birth' => $jobApplication->date_of_birth,
                            'state' => $jobApplication->state,
                            'lga' => $jobApplication->lga,
                            'residence_state' => $jobApplication->residence_state,
                            'certifications' => $jobApplication->certifications,
                            'skills' => $jobApplication->skills,
                            'cv_url' => $jobApplication->cv_url,
                            'experience_level' => $jobApplication->experience_level,
                            'nysc_status' => $jobApplication->nysc_status,
                            'nysc_completion_year' => $jobApplication->nysc_completion_year);

                           

                        $wc = 0;
                        $wr_size = count($columWorkName);
                        $jobapplication_work = json_decode($jobApplication->work, true);

                        if (is_array($jobapplication_work) || is_object($jobapplication_work)) {

                            foreach ($jobapplication_work as $work) {

                                // Log::info($work);
                                if (is_array($work) || is_object($work)) {
                                    $workArray = array();
                                    foreach ($columWorkName as $workname) {
//                                        if(isset($work[$workname]))
                                        $workArray[] = (isset($work[$workname]) && $work[$workname] != "" ? $work[$workname] : "null");
                                        //                                      else
                                        //                                          $workArray[] = "null";
                                    }
                                    // $wr_size = sizeof($work);
                                    // $rowdata = array_merge($rowdata, array_values($work));
                                    $wr_size = sizeof($workArray);
                                    $rowdata = array_merge($rowdata, array_values($workArray));
                                    $wc++;
                                }

                            }


                        }
                        $total_rem_work = $total_work - $wc;

                        for ($y = 0; $y < $total_rem_work; $y++) {
                            $rowdata = array_merge($rowdata, array_fill(0, $wr_size, null));
                        }


                        $ec = 0;
                        $edu_size = count($columEduName);
                        $jobapplication_education = json_decode($jobApplication->education, true);

                        if (is_array($jobapplication_education) || is_object($jobapplication_education)) {
                            foreach ($jobapplication_education as $education) {
                                if (is_array($education)) {

                                    $eduArray = array();
                                    foreach ($columEduName as $eduname) {
                                        $eduArray[] = ($education[$eduname] != "" ? $education[$eduname] : "null");
                                    }
                                    // $edu_size = sizeof($education);
                                    // $rowdata = array_merge($rowdata, array_values($education));

                                    $edu_size = sizeof($eduArray);
                                    $rowdata = array_merge($rowdata, array_values($eduArray));
                                    $ec++;
                                }


                            }

                        }
                        $total_rem_edu = $total_education - $ec;

                        for ($y = 0; $y < $total_rem_edu; $y++) {
                            $rowdata = array_merge($rowdata, array_fill(0, $edu_size, null));
                        }


                        $olc = 0;
                        $olev_size = 8;
                        $jobapplication_olevel = json_decode($jobApplication->olevel);


                        $jobapplication_olevel_scores = json_decode($jobApplication->olevel_scores);

                        if (is_array($jobapplication_olevel) || is_object($jobapplication_olevel)) {

                            foreach ($jobapplication_olevel as $olevel) {
                                //$olev_size = 3;
                                $rowdata[] = $olevel->type;
                                $otype = $olevel->type;

                                if (is_array($jobapplication_olevel_scores) || is_object($jobapplication_olevel_scores)) {
                                    foreach ($jobapplication_olevel_scores as $oresult) {
                                        if (!isset($oresult->type)) {
                                            $oresult->type = $otype;
                                        }

                                        if (trim($olevel->olevel) == trim($oresult->olevel))
                                            $rowdata = array_merge($rowdata, [$oresult->subject, $oresult->grade]);
                                    }
                                }

                                $olc++;
                            }

                        }

                        $total_rem_olev = $total_olevel - $olc;

                        for ($y = 0; $y < $total_rem_olev; $y++) {
                            $rowdata = array_merge($rowdata, array_fill(0, $olev_size, null));
                        }


                        fputcsv($file, $rowdata);
                    }
                });
                fclose($file);
            };
            $response = new StreamedResponse($callback, 200, $headers);
            return $response;

        }
        exit;
        if ($type == 'xlsx') {

            $defaultColumnsArray = array();
            $workcount = [];
            $educationcount = [];
            $candidateOlevelsCount = [];
            $CandidateOlevels = [];

            foreach ($jobApplications as $row) {

                $candidate_id = $row->candidate_id;
                $candidateWorkHistoryQuery = CandidateWorkHistory::where('candidate_id', $candidate_id);
                $candidateEducationQuery = CandidateEducation::where('candidate_id', $candidate_id);
                $candidateOlevelQuery = CandidateOlevel::where('candidate_id', $candidate_id);
                array_push($CandidateOlevels, $candidateOlevelQuery->with('results')->get());
                if ($candidateWorkHistoryQuery && $candidateEducationQuery) {
                    $workcount[] = $candidateWorkHistoryQuery->count();
                    $educationcount[] = $candidateEducationQuery->count();
                    $candidateOlevelsCounts[] = $candidateOlevelQuery->count();
                }
            }
            sort($workcount);
            sort($educationcount);
            sort($candidateOlevelsCounts);


            $tempwork = [];
            array_push($tempwork, 'S/N', 'Job Title', 'Name', 'Email', 'Mobile', 'Status', 'Registered On', 'Gender', 'DOB', 'State', 'L.G.A',
                'Certifications', 'Candidate ID', 'Skills', 'Resume', 'Experience Level', 'NYSC_Status', 'NYSC Completion Year');

            $columWorkName = Schema::getColumnListing('candidate_work_histories');
            for ($i = 0; $i < count($workcount); $i++) {
                foreach ($columWorkName as $columWorkNamedata) {
                    $tempwork[] = 'work_' . '' . $i . '_' . $columWorkNamedata;
                }
            }

            $columEduName = Schema::getColumnListing('candidate_educations');
            for ($j = 0; $j < count($educationcount); $j++) {
                foreach ($columEduName as $columnIndex => $columEduNamedata) {
                    $tempwork[] = 'education_' . '' . $j . '_' . $columEduNamedata;
                }
            }

            $columOlevelName = Schema::getColumnListing('candidate_olevels');
            array_push($columOlevelName, 'scores');

            for ($k = 0; $k < count($candidateOlevelsCounts); $k++) {
                foreach ($columOlevelName as $columOlevelNamedata) {
                    $tempwork[] = 'exam_' . '' . $k . '_' . $columOlevelNamedata;
                }
            }

            $defaultColumnsArray[] = $tempwork;

            foreach ($jobApplications as $row) {
                $rowToArrayOriginal = $row->toArray();
                $rowToArray = [];
                foreach ($rowToArrayOriginal as $rowToArrayOriginalData) {
                    if (!is_array($rowToArrayOriginalData)) {
                        array_push($rowToArray, $rowToArrayOriginalData);
                    }
                }

                $remove = ['work', 'education', 'olevel'];

                $tempWork = $rowToArrayOriginal['work'];
                $tempWorkArray = [];
                foreach ($tempWork as $tempWorkData) {
                    array_push($tempWorkArray, $tempWorkData);
                }

                $expectedWork = (count($columWorkName)) * count($workcount);
                $currentWork = count(array_flatten($tempWorkArray));
                $workLeft = $expectedWork - $currentWork;
                $flattened_work_array = array_flatten($tempWorkArray);

                for ($l = 0; $l < $workLeft; $l++) {
                    array_push($flattened_work_array, null);
                }

                $tempEdu = $row->toArray()['education'];
                $tempEduArray = [];
                foreach ($tempEdu as $tempEduData) {
                    array_push($tempEduArray, $tempEduData);
                }

                $expectedEdu = (count($columEduName)) * count($educationcount);
                $currentEdu = count(array_flatten($tempEduArray));
                $eduLeft = $expectedEdu - $currentEdu;
                $flattened_edu_array = array_flatten($tempEduArray);
                for ($m = 0; $m < $eduLeft; $m++) {
                    array_push($flattened_edu_array, null);
                }

                $tempOlevelArray = [];
                foreach ($CandidateOlevels as $CandidateOlevelsRow) {
                    foreach ($CandidateOlevelsRow as $CandidateOlevel) {
                        if (!empty($CandidateOlevel->toArray()['results'])) {

                            $tempOlevelArrayRaw = $CandidateOlevel->toArray();
                            $tempOlevelsResults = $CandidateOlevel->toArray()['results'];

                            $tempOlevelResultsArray = [];
                            foreach ($tempOlevelsResults as $a => $data) {

                                $removeOlevelfields = ['id', 'uuid', 'olevel', 'created_at', 'updated_at'];
                                $filteredata = array_diff_key($data, array_flip($removeOlevelfields));
                                array_push($tempOlevelResultsArray, $filteredata);
                            }

                            $olevelString = json_encode($tempOlevelResultsArray);
                            $tempOlevelArrayRaw['results'] = $olevelString;
                            array_push($tempOlevelArray, $tempOlevelArrayRaw);

                        }
                    }
                }

                $CandidateOlevels = [];
                $expectedOlevel = (count($columOlevelName)) * count($candidateOlevelsCounts);
                $currentOlevel = count(array_flatten($tempOlevelArray));
                $olevelLeft = $expectedOlevel - $currentOlevel;
                $flattened_olevel_array = array_flatten($tempOlevelArray);
                for ($n = 0; $n < $olevelLeft; $n++) {
                    array_push($flattened_olevel_array, null);
                }

                $defaultColumnsArray[] = array_merge(array_diff_key($rowToArray, array_flip($remove)), $flattened_work_array, $flattened_edu_array, $flattened_olevel_array);
            }

            Excel::create('job-applications', function ($excel) use ($defaultColumnsArray) {
                $excel->setTitle('Job Applications');
                $excel->setCreator('Recruit')->setCompany($this->companyName);
                $excel->setDescription('job-applications file');

                $excel->sheet('CandidateInfo', function ($sheet) use ($defaultColumnsArray) {
                    $sheet->fromArray($defaultColumnsArray, null, 'A1', false, false);
                    $sheet->row(1, function ($row) {
                        $row->setFont(array(
                            'bold' => true
                        ));

                    });
                });
            })->download('xlsx');

        }
    }

    public function getAssignedCandidates()
    {
        $candidateWorkHistoryQuery = JobApplicationTestGroup::where('candidate_id', $candidate_id);

    }

    public function getNigerianUniversities()
    {
        $universities = file_get_contents(base_path('resources/lang/en/nigerian_universities.json'));
        return $universities;
    }

    private function writeShortListFile($txt)
    {
        $fp = fopen("shortlist.txt", "w");
        fwrite($fp, $txt);
        fclose($fp);
    }

    private function readShortListFile()
    {
        $fp = fopen("shortlist.txt", "r");
        $content = fread($fp, filesize("shortlist.txt"));
        fclose($fp);
        return $content;
    }

    function getQuery($sql){
        $query = str_replace(array('?'), array('\'%s\''), $sql->toSql());
        $query = vsprintf($query, $sql->getBindings());     
        Log::info($query);
    }

}
?>
