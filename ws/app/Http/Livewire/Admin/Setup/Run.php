<?php
/**
 * Oncoreport Web Service
 *
 * @author S. Alaimo, Ph.D. <alaimos at gmail dot com>
 */

namespace App\Http\Livewire\Admin\Setup;

use App\Constants;
use App\Jobs\Request as JobRequest;
use App\Models\Job;
use Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Run extends Component
{
    use AuthorizesRequests;

    public string $cosmicUsername = '';
    public string $cosmicPassword = '';
    public bool $jobError = false;
    public string $errorLog = '';
    public ?Job $setupJob = null;

    /**
     * Get validation rules
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'cosmicUsername' => ['required', 'string'],
            'cosmicPassword' => ['required', 'string'],
        ];
    }

    /**
     * Prepare the component.
     *
     * @return void
     */
    public function mount(): void
    {
        $this->setupJob = Job::where('job_type', 'setup_job_type')
                             ->whereNotIn('status', [Constants::FAILED])
                             ->first();
    }

    /**
     * Handles form submission
     */
    public function submit(): void
    {
        $this->validate();
        $this->setupJob = Job::create([
            'job_type'       => 'setup_job_type',
            'sample_code'    => uniqid('SETUP-', true),
            'name'           => 'Oncoreport Setup Job',
            'status'         => Constants::QUEUED,
            'job_parameters' => [
                'cosmic_username' => $this->cosmicUsername,
                'cosmic_password' => $this->cosmicPassword,
            ],
            'job_output'     => [],
            'log'            => '',
            'patient_id'     => null,
            'owner_id'       => Auth::id(),
        ]);
        JobRequest::dispatch($this->setupJob);
    }

    /**
     * Refresh the component
     */
    public function refresh(): void
    {
        $this->setupJob = Job::where('job_type', 'setup_job_type')
                             ->whereNotIn('status', [Constants::FAILED])
                             ->first();
        if (is_null($this->setupJob)) {
            $this->jobError = true;
            $this->errorLog = Job::where('job_type', 'setup_job_type')
                                 ->where('status', Constants::FAILED)
                                 ->first()->log;
        }
    }

    public function back(): mixed
    {
        $this->setupJob = null;
        $this->jobError = false;
        $this->errorLog = '';
    }

    public function done(): mixed
    {
        $this->setupJob = Job::where('job_type', 'setup_job_type')
                             ->whereNotIn('status', [Constants::FAILED])
                             ->first();
        if (optional($this->setupJob)->status === Constants::COMPLETED) {
            $this->setupJob->delete();
        }

        return redirect()->route('dashboard');
    }


    /**
     * Render this component
     *
     * @return mixed
     */
    public function render(): mixed
    {
        return view('livewire.admin.setup.run');
    }
}
