<?php

namespace ShopExtensions\Model;

use SilverShop\Model\Order;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;

/**
 * Persistent, gap-free counter for document numbers (invoice numbers).
 *
 * Why a table instead of "MAX(InvoiceNumber) + 1": the maximum over existing orders goes DOWN when
 * the order with the highest number is deleted (test orders, GDPR clean-up, a CMS delete), and the
 * next order then gets the SAME number again. Two concurrent payments can also both read the same
 * maximum. An invoice number must never be issued twice (§14 UStG), so the last issued value is
 * stored here and only ever increases.
 *
 * {@see self::next()} is atomic:
 *  - MySQL/MariaDB: one UPDATE with LAST_INSERT_ID(expr) – the row lock serialises concurrent
 *    callers, the value is read back per connection without a race.
 *  - other databases: UPDATE + SELECT inside a transaction (the UPDATE holds the row lock).
 *
 * The counter never goes below a floor, so it heals itself when numbers were set by hand or imported
 * (floor = highest number currently on an order) and it starts at the configured start value.
 *
 * @property string $Name
 * @property int    $LastValue
 */
class NumberSequence extends DataObject
{
    private static $table_name = 'ShopExtensions_NumberSequence';

    private static $db = [
        'Name' => 'Varchar(50)',
        'LastValue' => 'BigInt',
    ];

    private static $indexes = [
        'Name' => ['type' => 'unique', 'columns' => ['Name']],
    ];

    private static $summary_fields = [
        'Name' => 'Name',
        'LastValue' => 'Zuletzt vergeben',
    ];

    /**
     * Name of the invoice number sequence.
     */
    public const INVOICE = 'invoice';

    /**
     * Next value of the sequence $name. Never returns a value that was returned before, and never a
     * value <= $floor (e.g. the highest number that currently exists on an order).
     *
     * @param string $name  Sequence name
     * @param int    $floor The result is at least $floor + 1
     */
    public static function next(string $name, int $floor = 0): int
    {
        self::ensureRow($name, $floor);

        $table = DataObject::getSchema()->tableName(self::class);
        $conn = DB::get_conn();

        if (in_array($conn->getDatabaseServer(), ['mysql', 'mariadb'], true)) {
            DB::prepared_query(
                "UPDATE \"$table\" SET \"LastValue\" = LAST_INSERT_ID(GREATEST(\"LastValue\", ?) + 1), \"LastEdited\" = NOW()"
                . " WHERE \"Name\" = ?",
                [$floor, $name]
            );

            return (int) DB::query('SELECT LAST_INSERT_ID()')->value();
        }

        $conn->transactionStart();
        try {
            DB::prepared_query(
                "UPDATE \"$table\" SET \"LastValue\" = (CASE WHEN \"LastValue\" > ? THEN \"LastValue\" ELSE ? END) + 1"
                . " WHERE \"Name\" = ?",
                [$floor, $floor, $name]
            );
            $value = (int) DB::prepared_query("SELECT \"LastValue\" FROM \"$table\" WHERE \"Name\" = ?", [$name])->value();
            $conn->transactionEnd();
        } catch (\Throwable $e) {
            $conn->transactionRollback();
            throw $e;
        }

        return $value;
    }

    /**
     * Next invoice number: at least $start, above every number already on an order, above every
     * number issued before (even if that order was deleted since).
     */
    public static function nextInvoiceNumber(int $start): int
    {
        $maxExisting = (int) DB::query(sprintf(
            'SELECT MAX("InvoiceNumber") FROM "%s"',
            DataObject::getSchema()->tableName(Order::class)
        ))->value();

        return self::next(self::INVOICE, max($start - 1, $maxExisting));
    }

    /**
     * Last issued value (0 when the sequence was never used). For display/diagnostics only.
     */
    public static function current(string $name): int
    {
        $table = DataObject::getSchema()->tableName(self::class);

        return (int) DB::prepared_query("SELECT \"LastValue\" FROM \"$table\" WHERE \"Name\" = ?", [$name])->value();
    }

    /**
     * Creates the counter row once (value = $floor, so the first next() returns $floor + 1). A
     * concurrent insert of the same name fails on the unique index and is ignored.
     */
    private static function ensureRow(string $name, int $floor): void
    {
        $table = DataObject::getSchema()->tableName(self::class);
        $exists = DB::prepared_query("SELECT COUNT(*) FROM \"$table\" WHERE \"Name\" = ?", [$name])->value();
        if ((int) $exists > 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        try {
            DB::prepared_query(
                "INSERT INTO \"$table\" (\"ClassName\", \"Name\", \"LastValue\", \"Created\", \"LastEdited\") VALUES (?, ?, ?, ?, ?)",
                [self::class, $name, $floor, $now, $now]
            );
        } catch (\Throwable $e) {
            // Unique index: another request created the row in the meantime – fine.
            $exists = DB::prepared_query("SELECT COUNT(*) FROM \"$table\" WHERE \"Name\" = ?", [$name])->value();
            if ((int) $exists === 0) {
                throw $e;
            }
        }
    }

    public function canView($member = null)
    {
        return \SilverStripe\Security\Permission::check('ADMIN', 'any', $member);
    }

    public function canEdit($member = null)
    {
        return false;
    }

    public function canDelete($member = null)
    {
        return false;
    }

    public function canCreate($member = null, $context = [])
    {
        return false;
    }
}
