<?php

namespace WP_SMS\Admin\LicenseManagement;

use Exception;
use WP_SMS\Components\RemoteRequest;
use WP_SMS\Exceptions\LicenseException;
use WP_SMS\Utils\AdminHelper;
use WP_SMS\Traits\TransientCacheTrait;

class ApiCommunicator
{
    use TransientCacheTrait;

    private $apiUrl = 'https://wp-sms-pro.com' . '/wp-json/wp-license-manager/v1';

    /**
     * Get the list of products (add-ons) from the API and cache it for 1 week.
     *
     * @return array
     * @throws Exception if there is an error with the API call
     */
    public function getProducts()
    {
        try {
            $remoteRequest = new RemoteRequest('GET', "{$this->apiUrl}/product/list");
            $plugins       = $remoteRequest->execute(false, true, WEEK_IN_SECONDS);

            if (empty($plugins) || !is_array($plugins)) {
                throw new Exception(
                    sprintf(__('No products were found. The API returned an empty response from the following URL: %s', 'wp-sms'), "{$this->apiUrl}/product/list")
                );
            }

        } catch (Exception $e) {
            throw new Exception(
            // translators: %s: Error message.
                sprintf(__('Unable to retrieve product list from the remote server, %s. Please check the remote server connection or your remote work configuration.', 'wp-sms'), $e->getMessage())
            );
        }

        return $plugins;
    }

    /**
     * Get the download link for the specified plugin using the license key.
     *
     * @param string $licenseKey
     * @param string $pluginSlug
     *
     * @return string|null The download URL if found, null otherwise
     * @throws Exception if the API call fails
     */
    public function getDownloadUrl($licenseKey, $pluginSlug)
    {
        $remoteRequest = new RemoteRequest('GET', "{$this->apiUrl}/product/download", [
            'license_key' => $licenseKey,
            'domain'      => home_url(),
            'plugin_slug' => $pluginSlug,
        ]);

        return $remoteRequest->execute(true, true, DAY_IN_SECONDS);
    }

    /**
     * Get the download URL for a specific plugin slug from the license status.
     *
     * @param string $licenseKey
     * @param string $pluginSlug
     *
     * @return string|null The download URL if found, null otherwise
     * @throws Exception
     */
    public function getDownloadUrlFromLicense($licenseKey, $pluginSlug)
    {
        // Validate the license and get the licensed products
        $licenseStatus = $this->validateLicense($licenseKey, $pluginSlug);

        // Search for the download URL in the licensed products
        foreach ($licenseStatus->products as $product) {
            if ($product->slug === $pluginSlug) {
                return isset($product->download_url) ? $product->download_url : null;
            }
        }

        return null;
    }

    /**
     * Validate the license and get the status of licensed products.
     *
     * @param string $licenseKey
     * @param string $product Optional param to check whether the license is valid for a particular product, or not
     *
     * @return object License status
     * @throws Exception if the API call fails
     */
    public function validateLicense($licenseKey, $product = false)
    {
        if (empty($licenseKey) || !AdminHelper::isStringLengthBetween($licenseKey, 32, 40) || !preg_match('/^[a-zA-Z0-9]+$/', $licenseKey)) {
            throw new LicenseException(
                esc_html__('License key is not valid. Please enter a valid license and try again.', 'wp-sms'),
                'invalid_license'
            );
        }

        $remoteRequest = new RemoteRequest('GET', "{$this->apiUrl}/license/status", [
            'license_key' => $licenseKey,
            'domain'      => home_url(),
        ]);

        $licenseData = $remoteRequest->execute(false, false);

        if (empty($licenseData)) {
            throw new LicenseException(__('Invalid license response!', 'wp-sms'));
        }

        if (empty($licenseData->license_details)) {
            $message = isset($licenseData) && is_object($licenseData) && isset($licenseData->message)
                ? $licenseData->message
                : esc_html__('Unknown error!', 'wp-sms');

            $status = isset($licenseData) && is_object($licenseData) && isset($licenseData->status)
                ? $licenseData->status
                : '';

            $code = isset($licenseData) && is_object($licenseData) && isset($licenseData->code)
                ? intval($licenseData->code)
                : 0;

            throw new LicenseException(
                $message,
                $status,
                $code
            );

        }

        if (!empty($product)) {
            $productSlugs = array_column($licenseData->products, 'slug');

            if (!in_array($product, $productSlugs, true)) {
                throw new LicenseException(sprintf(__('The license is not related to the requested Add-On <b>%s</b>.', 'wp-sms'), $product));
            }
        }

        LicenseHelper::storeLicense($licenseKey, $licenseData);

        return $licenseData;
    }
}
