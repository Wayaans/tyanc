<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserProfileFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UserProfile extends Model
{
    /** @use HasFactory<UserProfileFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'phone_number',
        'date_of_birth',
        'gender',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'country',
        'postal_code',
        'company_name',
        'job_title',
        'bio',
        'social_links',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fullName(): ?string
    {
        $fullName = mb_trim(collect([$this->first_name, $this->last_name])->filter()->implode(' '));

        return $fullName === '' ? null : $fullName;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'user_id' => 'string',
            'date_of_birth' => 'date',
            'social_links' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
