<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\App;
use App\Models\ApprovalRequest;
use App\Models\Conversation;
use App\Models\FileLibrary;
use App\Models\ImportRun;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewApprovalRequestedNotification;
use App\Notifications\NewMessageNotification;
use App\Notifications\UserStatusChangedNotification;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

final class TyancDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AppRegistrySeeder::class,
            RolesAndPermissionsSeeder::class,
            AccessMatrixSeeder::class,
        ]);

        $users = DB::transaction(function (): array {
            $indonesian = FakerFactory::create('id_ID');
            $english = FakerFactory::create('en_US');

            $users = $this->seedUsers($indonesian, $english);
            $this->seedCustomApp();
            $approvalRequest = $this->seedApprovalFlow($users['operations_lead']);
            [$conversation, $latestMessage] = $this->seedConversation($users['support_lead'], $users['admin'], $users['demo_analyst']);
            $this->seedNotifications($users, $approvalRequest, $conversation, $latestMessage);

            return $users;
        });

        $this->seedFiles($users['operations_lead']);
    }

    /**
     * @return array{admin: User, operations_lead: User, support_lead: User, demo_analyst: User, access_auditor: User, product_coordinator: User, inactive_user: User}
     */
    private function seedUsers(Generator $indonesian, Generator $english): array
    {
        $admin = $this->upsertUser(
            attributes: [
                'username' => 'manuse',
                'email' => 'manuse@app.com',
                'password' => 'password',
                'status' => UserStatus::Active,
                'timezone' => 'Asia/Makassar',
                'locale' => 'en',
                'email_verified_at' => now()->subDays(30),
                'last_login_at' => now()->subMinutes(25),
                'last_login_ip' => '10.0.0.10',
                'avatar' => null,
            ],
            profile: [
                'first_name' => 'Manuse',
                'last_name' => 'Operator',
                'phone_number' => '+6281234500001',
                'date_of_birth' => '1989-05-11',
                'gender' => 'male',
                'address_line_1' => 'Jl. Veteran No. 18',
                'address_line_2' => 'Lantai 2',
                'city' => 'Makassar',
                'state' => 'Sulawesi Selatan',
                'country' => 'ID',
                'postal_code' => '90111',
                'company_name' => 'Tyanc',
                'job_title' => 'Workspace Administrator',
                'bio' => 'Keeps the Tyanc control plane ready for daily operations.',
                'social_links' => [
                    'linkedin' => 'https://linkedin.com/in/manuse-operator',
                ],
            ],
            preferences: [
                'locale' => 'en',
                'timezone' => 'Asia/Makassar',
                'appearance' => 'dark',
                'sidebar_variant' => 'inset',
                'spacing_density' => 'default',
            ],
            roles: [(string) config('tyanc.reserved_roles.admin')],
        );

        $operationsLead = $this->upsertUser(
            attributes: [
                'username' => 'naya-rahma',
                'email' => 'naya.rahma@tyanc.test',
                'password' => 'password',
                'status' => UserStatus::Active,
                'timezone' => 'Asia/Jakarta',
                'locale' => 'id',
                'email_verified_at' => now()->subDays(18),
                'last_login_at' => now()->subHour(),
                'last_login_ip' => '10.0.0.24',
                'avatar' => null,
            ],
            profile: [
                'first_name' => 'Naya',
                'last_name' => 'Rahma',
                'phone_number' => '+6281234500002',
                'date_of_birth' => '1992-09-03',
                'gender' => 'female',
                'address_line_1' => $indonesian->streetAddress(),
                'address_line_2' => sprintf('Blok %s', $indonesian->buildingNumber()),
                'city' => 'Jakarta',
                'state' => 'DKI Jakarta',
                'country' => 'ID',
                'postal_code' => $indonesian->postcode(),
                'company_name' => 'Tyanc',
                'job_title' => 'Operations Lead',
                'bio' => 'Memimpin approval workflow dan kesiapan operasional lintas aplikasi.',
                'social_links' => [
                    'linkedin' => 'https://linkedin.com/in/naya-rahma',
                ],
            ],
            preferences: [
                'locale' => 'id',
                'timezone' => 'Asia/Jakarta',
                'appearance' => 'light',
                'sidebar_variant' => 'inset',
                'spacing_density' => 'comfortable',
            ],
            roles: ['Operations Lead'],
        );

        $supportLead = $this->upsertUser(
            attributes: [
                'username' => 'dewi-lestari',
                'email' => 'dewi.lestari@tyanc.test',
                'password' => 'password',
                'status' => UserStatus::Active,
                'timezone' => 'Asia/Jakarta',
                'locale' => 'id',
                'email_verified_at' => now()->subDays(9),
                'last_login_at' => now()->subMinutes(8),
                'last_login_ip' => '10.0.0.35',
                'avatar' => null,
            ],
            profile: [
                'first_name' => 'Dewi',
                'last_name' => 'Lestari',
                'phone_number' => '+6281234500003',
                'date_of_birth' => '1994-02-21',
                'gender' => 'female',
                'address_line_1' => $indonesian->streetAddress(),
                'address_line_2' => sprintf('Blok %s', $indonesian->buildingNumber()),
                'city' => 'Bandung',
                'state' => 'Jawa Barat',
                'country' => 'ID',
                'postal_code' => $indonesian->postcode(),
                'company_name' => 'Tyanc',
                'job_title' => 'Support Lead',
                'bio' => 'Menjaga kualitas onboarding, percakapan internal, dan tindak lanjut pengguna.',
                'social_links' => [
                    'github' => 'https://github.com/dewi-lestari',
                ],
            ],
            preferences: [
                'locale' => 'id',
                'timezone' => 'Asia/Jakarta',
                'appearance' => 'system',
                'sidebar_variant' => 'floating',
                'spacing_density' => 'default',
            ],
            roles: ['Support Lead'],
        );

        $demoAnalyst = $this->upsertUser(
            attributes: [
                'username' => 'rio-pratama',
                'email' => 'rio.pratama@demo.test',
                'password' => 'password',
                'status' => UserStatus::Active,
                'timezone' => 'Asia/Jakarta',
                'locale' => 'id',
                'email_verified_at' => now()->subDays(11),
                'last_login_at' => now()->subMinutes(42),
                'last_login_ip' => '10.0.0.52',
                'avatar' => null,
            ],
            profile: [
                'first_name' => 'Rio',
                'last_name' => 'Pratama',
                'phone_number' => '+6281234500004',
                'date_of_birth' => '1991-12-09',
                'gender' => 'male',
                'address_line_1' => $indonesian->streetAddress(),
                'address_line_2' => sprintf('Blok %s', $indonesian->buildingNumber()),
                'city' => 'Surabaya',
                'state' => 'Jawa Timur',
                'country' => 'ID',
                'postal_code' => $indonesian->postcode(),
                'company_name' => 'Demo Lab',
                'job_title' => 'Demo Analyst',
                'bio' => 'Mengawasi kualitas demo dashboard dan skenario QA lintas tim.',
                'social_links' => [
                    'linkedin' => 'https://linkedin.com/in/rio-pratama',
                ],
            ],
            preferences: [
                'locale' => 'id',
                'timezone' => 'Asia/Jakarta',
                'appearance' => 'light',
                'sidebar_variant' => 'sidebar',
                'spacing_density' => 'compact',
            ],
            roles: ['Demo Analyst'],
        );

        $accessAuditor = $this->upsertUser(
            attributes: [
                'username' => 'jules-barton',
                'email' => 'jules.barton@tyanc.test',
                'password' => 'password',
                'status' => UserStatus::Active,
                'timezone' => 'UTC',
                'locale' => 'en',
                'email_verified_at' => now()->subDays(21),
                'last_login_at' => now()->subDays(1),
                'last_login_ip' => '10.0.0.63',
                'avatar' => null,
            ],
            profile: [
                'first_name' => 'Jules',
                'last_name' => 'Barton',
                'phone_number' => $english->e164PhoneNumber(),
                'date_of_birth' => '1988-07-18',
                'gender' => 'non_binary',
                'address_line_1' => $english->streetAddress(),
                'address_line_2' => $english->secondaryAddress(),
                'city' => 'Singapore',
                'state' => 'Central Region',
                'country' => 'SG',
                'postal_code' => $english->postcode(),
                'company_name' => 'Tyanc',
                'job_title' => 'Access Auditor',
                'bio' => 'Reviews the RBAC catalog and governance changes before release windows.',
                'social_links' => [
                    'github' => 'https://github.com/jules-barton',
                ],
            ],
            preferences: [
                'locale' => 'en',
                'timezone' => 'UTC',
                'appearance' => 'dark',
                'sidebar_variant' => 'inset',
                'spacing_density' => 'comfortable',
            ],
            roles: ['Access Auditor'],
        );

        $productCoordinator = $this->upsertUser(
            attributes: [
                'username' => 'maya-kusuma',
                'email' => 'maya.kusuma@tyanc.test',
                'password' => 'password',
                'status' => UserStatus::PendingVerification,
                'timezone' => 'Asia/Makassar',
                'locale' => 'id',
                'email_verified_at' => null,
                'last_login_at' => null,
                'last_login_ip' => null,
                'avatar' => null,
            ],
            profile: [
                'first_name' => 'Maya',
                'last_name' => 'Kusuma',
                'phone_number' => '+6281234500005',
                'date_of_birth' => '1996-03-14',
                'gender' => 'female',
                'address_line_1' => $indonesian->streetAddress(),
                'address_line_2' => sprintf('Blok %s', $indonesian->buildingNumber()),
                'city' => 'Denpasar',
                'state' => 'Bali',
                'country' => 'ID',
                'postal_code' => $indonesian->postcode(),
                'company_name' => 'Tyanc',
                'job_title' => 'Product Coordinator',
                'bio' => 'Menjembatani prioritas produk dengan rollout demo dan operasional.',
                'social_links' => [
                    'linkedin' => 'https://linkedin.com/in/maya-kusuma',
                ],
            ],
            preferences: [
                'locale' => 'id',
                'timezone' => 'Asia/Makassar',
                'appearance' => 'system',
                'sidebar_variant' => 'inset',
                'spacing_density' => 'default',
            ],
            roles: ['Support Lead', 'Demo Analyst'],
        );

        $inactiveUser = $this->upsertUser(
            attributes: [
                'username' => 'alex-harper',
                'email' => 'alex.harper@tyanc.test',
                'password' => 'password',
                'status' => UserStatus::Suspended,
                'timezone' => 'UTC',
                'locale' => 'en',
                'email_verified_at' => now()->subDays(60),
                'last_login_at' => now()->subDays(14),
                'last_login_ip' => '10.0.0.91',
                'avatar' => null,
            ],
            profile: [
                'first_name' => 'Alex',
                'last_name' => 'Harper',
                'phone_number' => $english->e164PhoneNumber(),
                'date_of_birth' => '1990-10-27',
                'gender' => 'prefer_not_to_say',
                'address_line_1' => $english->streetAddress(),
                'address_line_2' => $english->secondaryAddress(),
                'city' => 'Melbourne',
                'state' => 'VIC',
                'country' => 'AU',
                'postal_code' => $english->postcode(),
                'company_name' => 'Tyanc',
                'job_title' => 'Temporary Reviewer',
                'bio' => 'Paused while awaiting access review and role reassignment.',
                'social_links' => [
                    'github' => 'https://github.com/alex-harper',
                ],
            ],
            preferences: [
                'locale' => 'en',
                'timezone' => 'UTC',
                'appearance' => 'light',
                'sidebar_variant' => 'floating',
                'spacing_density' => 'compact',
            ],
            roles: [],
        );

        return [
            'admin' => $admin,
            'operations_lead' => $operationsLead,
            'support_lead' => $supportLead,
            'demo_analyst' => $demoAnalyst,
            'access_auditor' => $accessAuditor,
            'product_coordinator' => $productCoordinator,
            'inactive_user' => $inactiveUser,
        ];
    }

    private function seedCustomApp(): void
    {
        $app = App::query()->updateOrCreate(
            ['key' => 'tasks'],
            [
                'label' => 'Tasks',
                'route_prefix' => 'tasks',
                'icon' => 'layout-grid',
                'permission_namespace' => 'tasks',
                'enabled' => false,
                'sort_order' => 30,
                'is_system' => false,
            ],
        );

        $app->pages()->updateOrCreate(
            ['key' => 'dashboard'],
            [
                'label' => 'Dashboard',
                'route_name' => null,
                'path' => '/tasks/dashboard',
                'permission_name' => 'tasks.dashboard.viewany',
                'sort_order' => 0,
                'enabled' => true,
                'is_navigation' => true,
                'is_system' => false,
            ],
        );

        $app->pages()->updateOrCreate(
            ['key' => 'backlog'],
            [
                'label' => 'Backlog',
                'route_name' => null,
                'path' => '/tasks/backlog',
                'permission_name' => 'tasks.backlog.viewany',
                'sort_order' => 1,
                'enabled' => true,
                'is_navigation' => true,
                'is_system' => false,
            ],
        );
    }

    private function seedApprovalFlow(User $requester): ApprovalRequest
    {
        $importRun = ImportRun::query()->updateOrCreate(
            ['file_name' => 'q2-branch-onboarding.xlsx'],
            [
                'type' => ImportRun::TypeUsers,
                'status' => ImportRun::StatusPendingApproval,
                'processed_rows' => 0,
                'meta' => [
                    'rows_detected' => 48,
                    'source' => 'regional-ops',
                ],
                'failure_message' => null,
                'created_by_id' => $requester->id,
                'started_at' => null,
                'finished_at' => null,
            ],
        );

        return ApprovalRequest::query()->updateOrCreate(
            [
                'subject_type' => ImportRun::class,
                'subject_id' => $importRun->id,
            ],
            [
                'action' => 'tyanc.users.import',
                'status' => ApprovalRequest::StatusPending,
                'requested_by_id' => $requester->id,
                'reviewed_by_id' => null,
                'request_note' => 'Please approve the onboarding batch for the Makassar support team.',
                'review_note' => null,
                'payload' => [
                    'subject_label' => 'Makassar support onboarding import',
                    'action_url' => route('tyanc.users.index', absolute: false),
                ],
                'requested_at' => now()->subHours(3),
                'reviewed_at' => null,
            ],
        );
    }

    /**
     * @return array{Conversation, Message}
     */
    private function seedConversation(User $author, User $admin, User $observer): array
    {
        $conversation = Conversation::query()->firstOrCreate(
            ['subject' => 'Phase 9 rollout checkpoint'],
            [
                'created_by_id' => $author->id,
                'last_message_at' => now()->subMinutes(20),
            ],
        );

        $conversation->participants()->syncWithoutDetaching([
            (string) $author->id => [
                'last_read_at' => now()->subMinutes(10),
                'archived_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            (string) $admin->id => [
                'last_read_at' => now()->subMinutes(30),
                'archived_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            (string) $observer->id => [
                'last_read_at' => null,
                'archived_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Message::query()->firstOrCreate(
            [
                'conversation_id' => $conversation->id,
                'sender_id' => $author->id,
                'body' => 'Import review is ready. I left notes for the access-matrix follow-up.',
            ],
        );

        Message::query()->firstOrCreate(
            [
                'conversation_id' => $conversation->id,
                'sender_id' => $admin->id,
                'body' => 'Great. Please keep the support and analyst roles visible in the next demo pass.',
            ],
        );

        $latestMessage = Message::query()->firstOrCreate(
            [
                'conversation_id' => $conversation->id,
                'sender_id' => $observer->id,
                'body' => 'I verified the permission catalog and the Indonesian copy is ready for review.',
            ],
        );

        $conversation->forceFill([
            'last_message_at' => $latestMessage->created_at ?? now(),
        ])->save();

        return [$conversation->fresh(['participants.profile', 'messages.sender.profile']), $latestMessage->fresh()];
    }

    /**
     * @param  array{admin: User, operations_lead: User, support_lead: User, demo_analyst: User, access_auditor: User, product_coordinator: User, inactive_user: User}  $users
     */
    private function seedNotifications(array $users, ApprovalRequest $approvalRequest, Conversation $conversation, Message $latestMessage): void
    {
        if (! $users['admin']->notifications()->where('type', NewApprovalRequestedNotification::class)->exists()) {
            $users['admin']->notify(new NewApprovalRequestedNotification($approvalRequest));
        }

        if (! $users['support_lead']->notifications()->where('type', NewMessageNotification::class)->exists()) {
            $users['support_lead']->notify(new NewMessageNotification($conversation, $latestMessage, $users['demo_analyst']));
        }

        if (! $users['inactive_user']->notifications()->where('type', UserStatusChangedNotification::class)->exists()) {
            $users['inactive_user']->notify(new UserStatusChangedNotification(
                $users['inactive_user'],
                UserStatus::Active->value,
                UserStatus::Suspended->value,
            ));
        }
    }

    private function seedFiles(User $uploadedBy): void
    {
        $library = FileLibrary::shared();

        $this->seedLibraryFile(
            library: $library,
            uploadedBy: $uploadedBy,
            fileName: 'tyanc-user-import-template.csv',
            content: implode("\n", [
                'username,email,locale,timezone,role',
                'ana.pradana,ana.pradana@example.com,id,Asia/Jakarta,Support Lead',
                'miles.carter,miles.carter@example.com,en,UTC,Access Auditor',
            ]),
        );

        $this->seedLibraryFile(
            library: $library,
            uploadedBy: $uploadedBy,
            fileName: 'access-matrix-review-notes.txt',
            content: implode("\n", [
                'Review focus:',
                '- Validate tyanc roles against permission source of truth.',
                '- Confirm demo dashboard access stays hidden for disabled apps.',
                '- Re-check Indonesian copy on the approval workflow.',
            ]),
        );
    }

    private function seedLibraryFile(FileLibrary $library, User $uploadedBy, string $fileName, string $content): void
    {
        $existingFile = $library
            ->getMedia(FileLibrary::FilesCollection)
            ->first(fn ($media): bool => $media->file_name === $fileName);

        if ($existingFile !== null) {
            return;
        }

        $directory = storage_path('app/seeders');
        File::ensureDirectoryExists($directory);

        $path = sprintf('%s/%s', $directory, $fileName);
        File::put($path, $content);

        $library
            ->addMedia($path)
            ->preservingOriginal()
            ->usingName(pathinfo($fileName, PATHINFO_FILENAME))
            ->usingFileName($fileName)
            ->withCustomProperties([
                'uploaded_by_id' => (string) $uploadedBy->id,
                'uploaded_by_name' => $uploadedBy->name,
            ])
            ->toMediaCollection(FileLibrary::FilesCollection);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $preferences
     * @param  list<string>  $roles
     */
    private function upsertUser(array $attributes, array $profile, array $preferences, array $roles): User
    {
        $email = (string) $attributes['email'];
        $user = User::query()->withTrashed()->firstOrNew(['email' => $email]);

        $name = collect([
            $profile['first_name'] ?? null,
            $profile['last_name'] ?? null,
        ])->filter()->implode(' ');

        $user->forceFill([
            ...$attributes,
            ...$this->legacyNameAttributes($name),
        ]);
        $user->save();

        if ($user->trashed()) {
            $user->restore();
        }

        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            $profile,
        );

        $user->preference()->updateOrCreate(
            ['user_id' => $user->id],
            [
                ...$preferences,
                'user_id' => $user->id,
            ],
        );

        $user->syncRoles($roles);

        return $user->fresh(['profile', 'preference', 'roles']);
    }

    /**
     * @return array<string, string>
     */
    private function legacyNameAttributes(string $name): array
    {
        if (! Schema::hasColumn('users', 'name') || $name === '') {
            return [];
        }

        return ['name' => $name];
    }
}
