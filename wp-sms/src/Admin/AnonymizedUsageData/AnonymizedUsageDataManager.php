<?php

namespace WP_SMS\Admin\AnonymizedUsageData;

use WP_SMS\Components\Event;
use WP_SMS\Utils\OptionUtil as Option;

class AnonymizedUsageDataManager
{
    /**
     * AnonymizedUsageDataManager constructor.
     *
     * This method hooks into the 'cron_schedules' filter to add a custom cron interval,
     * and schedules a cron event to run the 'anonymizedUsageData' method every two months.
     */
    public function __construct()
    {
        if (Option::get('share_anonymous_data')) {
            add_filter('cron_schedules', [$this, 'anonymizedUsageDataCronIntervalsHook']);
            Event::schedule('wp_sms_anonymized_share_data_hook', time(), 'every_two_months', [$this, 'sendAnonymizedUsageData']);
        } else {
            Event::unschedule('wp_sms_anonymized_share_data_hook');
        }
    }

    /**
     * Registers a custom cron schedule for anonymized usage data
     *
     * @param array $schedules Existing cron schedules.
     *
     * @return array Modified cron schedules with an added "every_two_months" interval.
     */
    public function anonymizedUsageDataCronIntervalsHook($schedules)
    {
        $schedules['every_two_months'] = array(
            'interval' => 60 * 60 * 24 * 60,
            'display'  => __('Every 2 Months', 'wp-sms')
        );
        return $schedules;
    }

    /**
     * Sends anonymized usage data to the remote API.
     */
    public function sendAnonymizedUsageData()
    {
        $anonymizedUsageDataSender = new AnonymizedUsageDataSender();

        $anonymizedUsageDataSender->sendAnonymizedUsageData($this->getAnonymizedUsageData());
    }

    /**
     * Retrieve anonymized usage data.
     *
     * @return array
     */
    public function getAnonymizedUsageData()
    {
        $data = [
            'domain'            => AnonymizedUsageDataProvider::getHomeUrl(),
            'wordpress_version' => AnonymizedUsageDataProvider::getWordPressVersion(),
            'php_version'       => AnonymizedUsageDataProvider::getPhpVersion() ?: 'not available',
            'plugin_version'    => AnonymizedUsageDataProvider::getPluginVersion(),
            'database_version'  => AnonymizedUsageDataProvider::getDatabaseVersion() ?: 'not available',
            'server_info'       => AnonymizedUsageDataProvider::getServerInfo(),
            'theme_info'        => AnonymizedUsageDataProvider::getThemeInfo(),
            'plugins'           => AnonymizedUsageDataProvider::getAllPlugins(),
            'settings'          => AnonymizedUsageDataProvider::getPluginSettings(),
            'timezone'          => AnonymizedUsageDataProvider::getTimezone(),
            'language'          => AnonymizedUsageDataProvider::getLocale(),
            'licenses_info'     => AnonymizedUsageDataProvider::getLicensesInfo(),
            'payload'           => AnonymizedUsageDataProvider::getPayload(),
        ];

        return apply_filters('wp_sms_anonymized_usage_data', $data);
    }

}