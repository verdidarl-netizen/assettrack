<?php
/** Activity / audit log. */
class Log
{
    public static function add(string $action, string $entity, ?string $entityId = null, string $details = ''): void
    {
        Database::insert('audit_log', 'id', [
            'actor_user_id' => Auth::id() ?: null,
            'actor_name'    => Auth::user()['name'] ?? 'system',
            'action' => $action, 'entity' => $entity, 'entity_id' => $entityId, 'details' => mb_substr($details, 0, 255),
        ]);
    }
    public static function recent(int $n = 200): array
    {
        return Database::all('SELECT * FROM audit_log ORDER BY id DESC LIMIT ' . (int)$n);
    }
}
