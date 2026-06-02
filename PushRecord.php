<?php

namespace Unisante\PushRecord;

use REDCap;

class PushRecord extends \ExternalModules\AbstractExternalModule
{
    protected const REDCAP_SAVE_RECORD = '1';
    protected const REDCAP_SURVEY_COMPLETE = '0';

    protected const TARGET_RECORD_ID_WITH_PREFIX = '1';

    public function redcap_save_record(int $project_id, string $record, string $instrument, int $event_id, ?int $group_id = NULL, ?string $survey_hash = NULL, ?int $response_id = NULL, int $repeat_instance = 1)
    {
        if ($this->getProjectSetting('method_to_trigger') !== self::REDCAP_SAVE_RECORD) {
            return;
        }
        $decodedRecord = $this->getRecord($record);

        // If the triggering value is not trueish, skip.
        $triggeringValueKey = $this->getProjectSetting('triggering_value');
        if (!($decodedRecord[$triggeringValueKey] ?? FALSE)) {
            return;
        }

        $this->pushRecord($record, $decodedRecord);
    }

    public function redcap_survey_complete(int $project_id, ?string $record, string $instrument, int $event_id, ?int $group_id, string $survey_hash, ?int $response_id = NULL, int $repeat_instance = 1)
    {
        if ($this->getProjectSetting('method_to_trigger') !== self::REDCAP_SURVEY_COMPLETE) {
            return;
        }

        // If it's not the configured survey, end here.
        $instrumentNameValueKey = $this->getProjectSetting('intrument_name');
        if ($instrument !== $instrumentNameValueKey) {
            return;
        }

        $decodedRecord = $this->getRecord($record);
        $this->pushRecord($record, $decodedRecord);
    }

    /**
     * Get record data.
     *
     * @param $recordId
     * @return mixed
     */
    protected function getRecord($recordId)
    {
        $records = REDCap::getData('json', $recordId);
        $decodedRecords = json_decode($records, true);
        return reset($decodedRecords);
    }

    /**
     * Push the new record to the target project.
     *
     * @param $record
     * @param $decodedRecord
     */
    protected function pushRecord($record, $decodedRecord)
    {
        $targetRecordIdFieldName = $this->getProjectSetting('target_record_id');
        $targetRecordId = $this->getTargetRecordId($record, $decodedRecord);

        $newRecord = [
            $targetRecordIdFieldName => $targetRecordId,
        ];

        $multipleValues = [];
        $fieldsList = $this->getSubSettings('fields');
        foreach ($fieldsList as $correspondance) {
            $sourceField = $correspondance['fields_field_source'];
            $destField = $correspondance['fields_field_destination'];

            if ($correspondance['fields_is_checkbox']) {
                $multipleValues[] = $this->buildMultipleValue($decodedRecord, $sourceField, $destField);
            } else {
                $newRecord[$destField] = $decodedRecord[$sourceField] ?? 'UNKNOWN';
            }
        }

        // array_merge in the loop is greedy, so we merge only one time.
        $newRecord = array_merge($newRecord, ...$multipleValues);

        $targetProject = $this->getProjectSetting('target_project');
        $result = REDCap::saveData($targetProject, 'json', json_encode([$newRecord]));

        $errors = $result['errors'];
        if (!empty($errors)) {
            if (is_array($errors)) {
                $errors = implode(', ', $errors);
            }

            $email = $this->getProjectSetting('email');
            REDCap::email($email, $email, 'Project ' . $targetProject . ': error while inserting data from record ' . $record . ', pid source: ' . $this->getProjectId() . ' in dest project', $errors);
        }
    }

    /**
     * @param array $sourceRecord
     * @param string $sourceField
     * @param string $destField
     * @return array
     */
    protected function buildMultipleValue(array $sourceRecord, string $sourceField, string $destField): array
    {
        $sourceFieldPrefix = "{$sourceField}__";
        $destinationFieldPrefix = "{$destField}__";

        $checkboxSourceValues = array_filter($sourceRecord, static function ($key) use ($sourceFieldPrefix) {
            return strpos($key, $sourceFieldPrefix) === 0;
        }, ARRAY_FILTER_USE_KEY);

        $checkboxDestionationValues = [];

        foreach ($checkboxSourceValues as $key => $value) {
            $destinationKey = str_replace($sourceFieldPrefix, $destinationFieldPrefix, $key);
            $checkboxDestionationValues[$destinationKey] = $value;
        }

        return $checkboxDestionationValues;
    }

    /**
     * Get the target record id value.
     *
     * @param $record
     * @param $decodedRecord
     * @return string
     */
    protected function getTargetRecordId($record, $decodedRecord)
    {
        // Current record ID with prefix
        if ($this->getProjectSetting('record_id_or_variable') === self::TARGET_RECORD_ID_WITH_PREFIX) {
            $targetRecordIdPrefix = $this->getProjectSetting('target_record_id_prefix');
            $targetRecordIdSuffix = $this->getProjectSetting('target_record_id_suffix');
            return $targetRecordIdPrefix . $record . $targetRecordIdSuffix;
        }

        // RecordId value from a given field.
        $sourceRecordId = $this->getProjectSetting('variable');
        return $decodedRecord[$sourceRecordId];
    }
}
