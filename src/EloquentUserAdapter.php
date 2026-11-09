<?php

namespace Nine\Laravel\Server;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Nine\Adapter\AdapterInterface;
use Nine\Adapter\AdapterUser;
use Nine\Laravel\Events\EventFactory;
use Nine\Laravel\Events\NineEvent;

/**
 * Adapter over an Eloquent user model, configured in `nine-server.user`. Extend it to
 * change lookups (e.g. scopes, soft deletes) or the claims returned (`transform()`).
 */
class EloquentUserAdapter implements AdapterInterface
{
    /** @var Hasher */
    protected $hasher;

    /** @var Dispatcher */
    protected $events;

    public function __construct(Hasher $hasher, Dispatcher $events)
    {
        $this->hasher = $hasher;
        $this->events = $events;
    }

    public function authenticate(array $identifier, string $password)
    {
        $model = $this->queryByIdentifier($identifier)?->first();
        if (!$model) {
            return self::INVALID;
        }

        $column = $this->column('password');
        $hash   = $column ? $model->getAttribute($column) : null;
        if (!is_string($hash) || $hash === '' || !$this->hasher->check($password, $hash)) {
            return self::INVALID;
        }

        if ($this->isBlocked($model)) {
            return self::BLOCKED;
        }

        if ($this->hasher->needsRehash($hash)) {
            $model->forceFill([$column => $this->hasher->make($password)])->save();
        }

        return $this->transform($model);
    }

    public function findUser(array $query): ?AdapterUser
    {
        if (isset($query['id'])) {
            $model = $this->newQuery()->where($this->column('id') ?? 'id', $query['id'])->first();
        } else {
            $model = $this->queryByIdentifier($query['identifier'] ?? [])?->first();
        }

        return $model ? $this->transform($model) : null;
    }

    public function onEvent(array $event): void
    {
        if (($event['type'] ?? null) === 'otp.requested' && config('nine-server.otp.send', true)) {
            $this->sendOtp($event['data'] ?? []);
        }

        if (config('nine-server.events.dispatch', true)) {
            foreach (EventFactory::all($event, NineEvent::SOURCE_ADAPTER) as $nineEvent) {
                $this->events->dispatch($nineEvent);
            }
        }
    }

    /**
     * Maps a model to the claims 9in stores (standard OIDC names).
     */
    public function transform(Model $model): AdapterUser
    {
        $metadata = $this->value($model, 'metadata');
        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true);
        }

        return new AdapterUser([
            'id'                    => (string) $this->value($model, 'id'),
            'email'                 => $this->value($model, 'email'),
            'email_verified'        => $this->flag($model, 'email_verified'),
            'phone_number'          => $this->value($model, 'phone_number'),
            'phone_number_verified' => $this->flag($model, 'phone_number_verified'),
            'username'              => $this->value($model, 'username'),
            'name'                  => $this->value($model, 'name'),
            'given_name'            => $this->value($model, 'given_name'),
            'family_name'           => $this->value($model, 'family_name'),
            'picture'               => $this->value($model, 'picture'),
            'locale'                => $this->value($model, 'locale'),
            'zoneinfo'              => $this->value($model, 'zoneinfo'),
            'birthdate'             => $this->dateString($this->value($model, 'birthdate')),
            'blocked'               => $this->isBlocked($model),
            'metadata'              => is_array($metadata) ? $metadata : null,
        ]);
    }

    /**
     * @param array<string, mixed> $data otp.requested payload: userId, channel, to, code, purpose, expiresAt
     */
    protected function sendOtp(array $data): void
    {
        $channel = ($data['channel'] ?? null) === 'sms' ? 'sms' : 'email';
        $route   = config("nine-server.otp.channels.{$channel}", $channel === 'sms' ? 'vonage' : 'mail');
        $class   = config('nine-server.otp.notification', Notifications\OtpNotification::class);

        $notification = new $class($data, (array) config('nine-server.otp.vars', []), $route);
        if ($queue = config('nine-server.otp.queue')) {
            $notification->onQueue($queue);
        }

        Notification::route($route, (string) ($data['to'] ?? ''))->notify($notification);
    }

    protected function newQuery(): Builder
    {
        $class = config('nine-server.user.model');
        return $class::query();
    }

    /**
     * @param array<string, mixed> $identifier
     */
    protected function queryByIdentifier(array $identifier): ?Builder
    {
        $type  = $identifier['type'] ?? null;
        $value = trim((string) ($identifier['value'] ?? ''));
        if ($value === '' || !in_array($type, (array) config('nine-server.user.identifiers', []), true)) {
            return null;
        }

        $claim  = $type === 'phone' ? 'phone_number' : $type;
        $column = $this->column($claim);
        if (!$column) {
            return null;
        }

        $query = $this->newQuery();
        if ($type === 'phone') {
            return $query->where($column, $value);
        }
        $grammar = $query->getQuery()->getGrammar();

        return $query->whereRaw('LOWER(' . $grammar->wrap($column) . ') = ?', [mb_strtolower($value)]);
    }

    protected function column(string $claim): ?string
    {
        $column = config("nine-server.user.columns.{$claim}");
        return is_string($column) && trim($column) !== '' ? $column : null;
    }

    /**
     * @return mixed
     */
    protected function value(Model $model, string $claim)
    {
        $column = $this->column($claim);
        return $column ? $model->getAttribute($column) : null;
    }

    protected function flag(Model $model, string $claim): ?bool
    {
        if (!$this->column($claim)) {
            return null;
        }
        $value = $this->value($model, $claim);
        return $value !== null && $value !== false && $value !== 0 && $value !== '0' && $value !== '';
    }

    protected function isBlocked(Model $model): bool
    {
        return (bool) $this->flag($model, 'blocked');
    }

    /**
     * @param mixed $value
     */
    protected function dateString($value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        return $value === null ? null : substr((string) $value, 0, 10);
    }
}
