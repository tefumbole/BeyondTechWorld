<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MySQL rejects the automatic index names from the first billing deploy.
 * Add the same indexes under short names when they are still missing.
 */
class ShortenCloudBillingIndexNames extends Migration
{
    public function up()
    {
        $this->addIndex('cloud_subscription_payment_items', 'cloud_tenant_id', 'cloud_pay_item_tenant_idx');
        $this->addIndex('cloud_subscription_payment_items', 'cloud_subscription_id', 'cloud_pay_item_sub_idx');
        $this->addIndex('cloud_subscription_payment_items', 'cloud_plan_id', 'cloud_pay_item_plan_idx');
        $this->addIndex('cloud_whatsapp_connection_events', 'cloud_whatsapp_connection_id', 'wa_conn_event_conn_idx');
        $this->addIndex('cloud_whatsapp_connection_events', 'actor_user_id', 'wa_conn_event_actor_idx');
    }

    public function down()
    {
        $this->dropIndex('cloud_subscription_payment_items', 'cloud_pay_item_tenant_idx');
        $this->dropIndex('cloud_subscription_payment_items', 'cloud_pay_item_sub_idx');
        $this->dropIndex('cloud_subscription_payment_items', 'cloud_pay_item_plan_idx');
        $this->dropIndex('cloud_whatsapp_connection_events', 'wa_conn_event_conn_idx');
        $this->dropIndex('cloud_whatsapp_connection_events', 'wa_conn_event_actor_idx');
    }

    private function addIndex($table, $column, $index)
    {
        if (! Schema::hasTable($table) || $this->indexExists($table, $index)) {
            return;
        }
        Schema::table($table, function (Blueprint $blueprint) use ($column, $index) {
            $blueprint->index($column, $index);
        });
    }

    private function dropIndex($table, $index)
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $index)) {
            return;
        }
        Schema::table($table, function (Blueprint $blueprint) use ($index) {
            $blueprint->dropIndex($index);
        });
    }

    private function indexExists($table, $index)
    {
        $connection = Schema::getConnection();
        if ($connection->getDriverName() === 'sqlite') {
            $rows = $connection->select("PRAGMA index_list('".$table."')");
            foreach ($rows as $row) {
                if (isset($row->name) && $row->name === $index) {
                    return true;
                }
            }
            return false;
        }
        $rows = $connection->select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$index]);
        return count($rows) > 0;
    }
}
