<?php

namespace App\Livewire;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\BulkAction;
use Filament\Forms;
use ResourceBundle;
use App\Models\User;
use Filament\Tables;
use Livewire\Component;
use Filament\Actions\Action;
use Filament\Support\Colors\Color;
use App\SocialProviders\SsoProvider;
use Illuminate\Support\Facades\Http;
use Filament\Support\Enums\Alignment;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Contracts\HasTable;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Contracts\HasActions;
use Illuminate\Database\Eloquent\Collection;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Actions\Concerns\InteractsWithActions;
use App\Notifications\VerifyEmailChange;

class Profile extends Component implements HasForms, HasTable, HasActions
{
    use InteractsWithForms, InteractsWithTable, InteractsWithActions;

    public $name;
    public $email;
    public $username;
    public $locale;
    public $per_page_setting;
    public $notification_settings;
    public $date_locale;
    public $hide_from_leaderboard;
    public User $user;

    public function mount(): void
    {
        $this->user = auth()->user();

        $this->form->fill([
            'name' => $this->user->name,
            'username' => $this->user->username,
            'email' => $this->user->email,
            'notification_settings' => $this->user->notification_settings,
            'per_page_setting' => $this->user->per_page_setting ?? [5],
            'locale' => $this->user->locale,
            'date_locale' => $this->user->date_locale,
            'hide_from_leaderboard' => $this->user->hide_from_leaderboard,
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make(trans('auth.profile'))
                ->columns()
                ->schema([
                    TextInput::make('name')->label(trans('auth.name'))->required(),
                    TextInput::make('username')
                        ->label(trans('profile.username'))
                        ->helperText(trans('profile.username_description'))
                        ->required()
                        ->rules([
                            'alpha_dash'
                        ])
                        ->unique(table: User::class, column: 'username', ignorable: auth()->user()),
                    TextInput::make('email')
                        ->label(trans('auth.email'))
                        ->required()
                        ->email()
                        ->unique(table: User::class, column: 'email', ignorable: auth()->user()),
                    Select::make('locale')->label(trans('auth.locale'))->options($this->locales)->placeholder(trans('auth.locale_null_value')),
                    Select::make('date_locale')->label(trans('auth.date_locale'))->options($this->locales)->placeholder(trans('auth.date_locale_null_value')),
                ])->collapsible(),

            Grid::make(2)
                ->schema([
                    Section::make(trans('profile.notifications'))
                        ->columnSpan(1)
                        ->schema([
                            CheckboxList::make('notification_settings')
                                ->label(trans('profile.notification_settings'))
                                ->options([
                                    'receive_mention_notifications' => trans('profile.receive_mention_notifications'),
                                    'receive_comment_reply_notifications' => trans('profile.receive_comment_reply_notifications'),
                                ]),
                        ])->collapsible(),

                    Section::make(trans('profile.settings'))
                        ->columnSpan(1)
                        ->schema([
                            Select::make('per_page_setting')
                                                   ->label(trans('profile.per-page-setting'))
                                ->multiple()
                                ->options([
                                    5 => '5',
                                    10 => '10',
                                    15 => '15',
                                    25 => '25',
                                    50 => '50',
                                ])
                                ->required()
                                ->helperText(trans('profile.per-page-setting-helper'))
                                ->rules(['array', 'in:5,10,15,25,50']),

                            Toggle::make('hide_from_leaderboard')
                                ->label(trans('profile.hide-from-leaderboard'))
                                ->helperText(trans('profile.hide-from-leaderboard-helper'))
                        ])->collapsible(),
                ])

        ];
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        $emailChanged = $this->user->email !== $data['email'];

        if ($emailChanged) {
            // Store the pending email and send verification
            $this->user->update([
                'name' => $data['name'],
                'username' => $data['username'],
                'notification_settings' => $data['notification_settings'],
                'per_page_setting' => $data['per_page_setting'],
                'locale' => $data['locale'],
                'date_locale' => $data['date_locale'],
                'hide_from_leaderboard' => $data['hide_from_leaderboard'],
                'pending_email' => $data['email'],
                'pending_email_verified_at' => null,
            ]);

            // Send verification email to the new email address
            $tempUser = clone $this->user;
            $tempUser->email = $data['email'];
            $tempUser->notify(new VerifyEmailChange($data['email']));

            Notification::make('email-verification-sent')
                ->title('Email Verification Required')
                ->body('A verification link has been sent to your new email address. Please verify it to complete the change.')
                ->warning()
                ->send();
        } else {
            $this->user->update([
                'name' => $data['name'],
                'username' => $data['username'],
                'notification_settings' => $data['notification_settings'],
                'per_page_setting' => $data['per_page_setting'],
                'locale' => $data['locale'],
                'date_locale' => $data['date_locale'],
                'hide_from_leaderboard' => $data['hide_from_leaderboard'],
            ]);
        }

        if ($this->user->wasChanged('locale', 'date_locale')) {
            Notification::make('profile')
                ->title('Profile')
                ->body('Refresh the page to show locale changes.')
                ->info()
                ->send();
        }

        if (!$emailChanged) {
            Notification::make('profile-saved')
                ->title('Profile')
                ->body('Profile has been saved')
                ->success()
                ->send();
        }
    }

    public function logout()
    {
        auth()->logout();

        return redirect()->route('home');
    }

    public function logoutAction(): Action
    {
        return Action::make('logout')
            ->label(trans('profile.logout'))
            ->requiresConfirmation()
            ->modalAlignment(Alignment::Left)
            ->modalDescription('Are you sure you want to do this?')
            ->color(Color::Slate)
            ->action(fn () => $this->logout());
    }

    public function deleteAction(): Action
    {
        return Action::make('delete')
            ->label(trans('profile.delete-account'))
            ->color(Color::Red)
            ->requiresConfirmation()
            ->modalAlignment(Alignment::Left)
            ->modalDescription('Are you sure you want to do this?')
            ->schema([
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->helperText('Enter your account\'s email address to delete your account')
                    ->in([auth()->user()->email])
            ])
            ->action(fn () => $this->delete());
    }

    public function delete()
    {
        auth()->user()->delete();

        auth()->logout();

        return redirect()->route('home');
    }

    public function getLocalesProperty(): array
    {
        $locales = ResourceBundle::getLocales('');

        return collect($locales)
            ->mapWithKeys(fn ($locale) => [$locale => $locale])
            ->toArray();
    }

    public function render()
    {
        return view('livewire.profile', [
            'hasSsoLoginAvailable' => SsoProvider::isEnabled(),
        ]);
    }

    protected function getTableQuery(): Builder
    {
        return auth()->user()->userSocials()->latest()->getQuery();
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('name'),
            TextColumn::make('provider'),
            TextColumn::make('created_at')->label('Date')->sortable()->dateTime(),
        ];
    }

    protected function getTableBulkActions(): array
    {
        return [
            BulkAction::make('delete')
                ->action(function (Collection $records) {
                    foreach ($records as $record) {
                        $endpoint = config('services.sso.endpoints.revoke') ?? config('services.sso.url') . '/api/oauth/revoke';

                        $client = Http::withToken($record->access_token)->timeout(5);

                        if (config('services.sso.http_verify') === false) {
                            $client->withoutVerifying();
                        }

                        $client->delete($endpoint);

                        $record->delete();
                    }
                })
                ->deselectRecordsAfterCompletion()
                ->requiresConfirmation()
                ->color('danger')
                ->icon('heroicon-o-trash'),

        ];
    }
}
