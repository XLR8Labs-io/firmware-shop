<?php

namespace App\Livewire;

use App\Models\FileRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Ask the store for a file it doesn't have. Open to guests; signed-in customers also
 * see their earlier requests and the store's replies.
 */
class RequestFile extends Component
{
    public string $name = '';

    public string $contact = '';

    public string $brand = '';

    public string $model = '';

    public string $details = '';

    /**
     * Hidden from people; a bot that fills it in is quietly ignored.
     */
    public string $website = '';

    public ?string $notice = null;

    public function mount(): void
    {
        $user = Auth::user();

        if ($user instanceof User) {
            $this->name = $user->name;
            $this->contact = $user->email;
        }
    }

    public function submit(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'contact' => ['required', 'string', 'max:150'],
            'brand' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:150'],
            'details' => ['nullable', 'string', 'max:2000'],
        ], attributes: [
            'contact' => __('email, Telegram or WhatsApp'),
        ]);

        if ($this->website !== '') {
            $this->sent();

            return;
        }

        $rateLimitKey = 'file-request:'.(Auth::id() ?? request()->ip());

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $this->addError('model', __('You have sent five requests in the last hour. Please try again later.'));

            return;
        }

        RateLimiter::hit($rateLimitKey, 3600);

        FileRequest::query()->create([
            'user_id' => Auth::id(),
            'name' => $this->name,
            'contact' => $this->contact,
            'brand' => $this->brand,
            'model' => $this->model,
            'details' => $this->details === '' ? null : $this->details,
        ]);

        $this->sent();
    }

    public function render(): View
    {
        $user = Auth::user();

        return view('livewire.request-file', [
            'myRequests' => $user instanceof User ? $user->fileRequests()->latest('id')->limit(10)->get() : collect(),
        ]);
    }

    private function sent(): void
    {
        $this->reset('brand', 'model', 'details', 'website');
        $this->notice = __('Thanks, we have your request. We will contact you when the file is available.');
    }
}
