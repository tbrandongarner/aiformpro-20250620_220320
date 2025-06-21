<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AiIntegrationService {
    private $apiUrl;
    private $apiKey;
    private $timeout;
    private $model;
    private $temperature;
    private $maxTokens;

    public function __construct( $api_url = '', $api_key = '', $timeout = 60, $model = 'gpt-3.5-turbo', $temperature = 0.7, $max_tokens = 500 ) {
        $this->apiUrl      = $api_url ?: ( defined( 'AIPRO_API_URL' ) ? AIPRO_API_URL : '' );
        $this->apiKey      = $api_key ?: ( defined( 'AIPRO_API_KEY' ) ? AIPRO_API_KEY : '' );
        $this->timeout     = $timeout;
        $this->model       = apply_filters( 'aipro_request_model', $model );
        $this->temperature = apply_filters( 'aipro_request_temperature', $temperature );
        $this->maxTokens   = apply_filters( 'aipro_request_max_tokens', $max_tokens );

        if ( empty( $this->apiUrl ) ) {
            error_log( 'AiIntegrationService initialization error: API URL is empty.' );
        }
        if ( empty( $this->apiKey ) ) {
            error_log( 'AiIntegrationService initialization error: API Key is empty.' );
        }
    }

    public function generateQuestions( $topic, $audience, $goal ) {
        if ( empty( $this->apiUrl ) || empty( $this->apiKey ) ) {
            return new WP_Error( 'aipro_missing_credentials', 'API URL or API Key is missing.' );
        }

        $prompt   = $this->buildPrompt( $topic, $audience, $goal );
        $response = $this->makeRequest( $prompt );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return $this->parseResponse( $response );
    }

    private function buildPrompt( $topic, $audience, $goal ) {
        $prompt = sprintf(
            'Generate a list of insightful quiz questions about "%s" for an audience of %s, aiming to achieve %s. Return the questions as a JSON array.',
            $topic,
            $audience,
            $goal
        );

        return apply_filters( 'aipro_build_prompt', $prompt, $topic, $audience, $goal );
    }

    private function makeRequest( $prompt ) {
        $payload = array(
            'model'       => $this->model,
            'messages'    => array(
                array(
                    'role'    => 'user',
                    'content' => $prompt,
                ),
            ),
            'temperature' => $this->temperature,
            'max_tokens'  => $this->maxTokens,
        );
        $payload = apply_filters( 'aipro_request_payload', $payload, $prompt );

        $args = array(
            'headers'   => $this->getHeaders(),
            'body'      => wp_json_encode( $payload ),
            'timeout'   => $this->timeout,
            'sslverify' => true,
        );

        $response = wp_remote_post( $this->apiUrl, $args );

        if ( is_wp_error( $response ) ) {
            error_log( 'AiIntegrationService request error: ' . $response->get_error_message() );
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== intval( $code ) ) {
            $message = wp_remote_retrieve_response_message( $response );
            error_log( "AiIntegrationService unexpected HTTP code {$code}: {$message}" );
            return new WP_Error( 'aipro_http_error', "API returned HTTP code {$code}: {$message}" );
        }

        return $response;
    }

    private function getHeaders() {
        $headers = array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiKey,
        );

        return apply_filters( 'aipro_request_headers', $headers );
    }

    private function parseResponse( $response ) {
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( JSON_ERROR_NONE !== json_last_error() ) {
            error_log( 'AiIntegrationService JSON decode error: ' . json_last_error_msg() );
            return new WP_Error( 'aipro_json_error', 'Failed to decode API response.' );
        }

        if ( isset( $data['choices'][0]['message']['content'] ) ) {
            $content = $data['choices'][0]['message']['content'];
            $parsed  = json_decode( $content, true );

            if ( JSON_ERROR_NONE === json_last_error() && is_array( $parsed ) ) {
                return $parsed;
            }

            error_log( 'AiIntegrationService content JSON parse error: ' . json_last_error_msg() );
            return new WP_Error( 'aipro_content_parse_error', 'Failed to parse API content as JSON.' );
        }

        return new WP_Error( 'aipro_response_format', 'Unexpected API response format.' );
    }
}