<?php

namespace SilverStripe\SessionManager\Services;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\RememberLoginHash;
use SilverStripe\SessionManager\Models\LoginSession;

class GarbageCollectionService
{
    use Configurable;
    use Injectable;

    /**
     * Limit the number of records collected per run.
     */
    private static ?int $batch_remove_limit = null;

    /**
     * Delete expired LoginSession and RememberLoginHash records
     */
    public function collect(): void
    {
        $this->collectExpiredSessions();
        $this->collectImplicitlyExpiredSessions();
        $this->collectExpiredLoginHashes();
    }

    private function batchRemoveAll($datalist)
    {
        $limit = self::config()->get('batch_remove_limit');
        $limitedList = $datalist->limit($limit);
        DB::get_conn()->transactionStart();
        foreach ($limitedList as $record) {
            $record->delete();
        }
        DB::get_conn()->transactionEnd();
    }

    /**
     * Collect all non-persistent LoginSession records that are older than the session lifetime
     */
    private function collectExpiredSessions(): void
    {
        $lifetime = LoginSession::config()->get('default_session_lifetime');
        $sessions = LoginSession::get()->filter([
            'LastAccessed:LessThan' => date('Y-m-d H:i:s', time() - $lifetime),
            'Persistent' => 0
        ]);
        $this->batchRemoveAll($sessions);
    }

    /**
     * Collect all persistent LoginSession records where the associated RememberLoginHash has expired
     */
    private function collectImplicitlyExpiredSessions(): void
    {
        $sessions = LoginSession::get()->filter([
            'Persistent' => 1,
            'LoginHash.ExpiryDate:LessThan' => date('Y-m-d H:i:s')
        ]);
        $this->batchRemoveAll($sessions);
    }

    /**
     * Collect all RememberLoginHash records that have expired
     */
    private function collectExpiredLoginHashes(): void
    {
        $hashes = RememberLoginHash::get()->filter([
            'ExpiryDate:LessThan' => date('Y-m-d H:i:s')
        ]);
        $this->batchRemoveAll($hashes);
    }
}
