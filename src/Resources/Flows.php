<?php

declare(strict_types=1);

namespace Tratto\Resources;

/**
 * The API accepts flow calls only from keys with full ("*") permission: there is no flows scope.
 *
 * Config keys are the ones the API reads (tratto-api flowTriggerEval and flowStepExecutor); every value is a
 * string. An unknown key is not an API error, it is silently ignored, so PHPStan checks the key names here.
 *
 * @phpstan-type FlowTrigger (
 *     array{type: 'contact_joins_audience', config?: array<'audienceId', string>}
 *     | array{type: 'contact_tag_added'|'contact_tag_removed', config?: array<'tagName', string>}
 *     | array{type: 'email_event', config?: array<'eventType'|'emailId', string>}
 *     | array{type: 'manual'}
 * )
 * @phpstan-type FlowStep (
 *     array{id: string, type: 'send_email', config?: array<'templateId'|'subject'|'from'|'fromName'|'replyTo', string>}
 *     | array{id: string, type: 'wait', config?: array<'delay'|'unit', string>}
 *     | array{id: string, type: 'branch', config?: array<'conditionField'|'conditionOperator'|'conditionValue'|'trueNextStep'|'falseNextStep', string>}
 *     | array{id: string, type: 'update_contact', config?: array<'action'|'field'|'value'|'tag', string>}
 *     | array{id: string, type: 'webhook_call', config?: array<'url'|'method'|'secret', string>}
 * )
 */
final class Flows extends Resource
{
    /**
     * @param array<string, mixed> $params after, limit
     *
     * @return array<mixed> {data, pagination}
     */
    public function list(array $params = []): array
    {
        return $this->request('GET', '/v1/flows' . self::query($params)) ?? [];
    }

    /**
     * Creates a draft flow (manual trigger, no steps): set trigger and steps with update().
     *
     * @param array{name: string} $params
     *
     * @return array<mixed> {id}
     */
    public function create(array $params): array
    {
        return $this->requestData('POST', '/v1/flows', $params);
    }

    /** @return array<mixed> */
    public function get(string $id): array
    {
        return $this->requestData('GET', '/v1/flows/' . self::id($id));
    }

    /**
     * Trigger examples: contact_tag_added with config tagName; wait steps with config delay ("2") and
     * unit (seconds, minutes, hours, days). Steps of an active flow cannot be changed: deactivate first.
     *
     * @param array{name?: string, trigger?: FlowTrigger, steps?: list<FlowStep>} $params
     *
     * @return array<mixed>
     */
    public function update(string $id, array $params): array
    {
        return $this->requestData('PATCH', '/v1/flows/' . self::id($id), $params);
    }

    /** @return array<mixed> {id} */
    public function delete(string $id): array
    {
        return $this->requestData('DELETE', '/v1/flows/' . self::id($id));
    }

    /** @return array<mixed> */
    public function activate(string $id): array
    {
        return $this->requestData('POST', '/v1/flows/' . self::id($id) . '/activate', []);
    }

    /** @return array<mixed> */
    public function deactivate(string $id): array
    {
        return $this->requestData('POST', '/v1/flows/' . self::id($id) . '/deactivate', []);
    }
}
