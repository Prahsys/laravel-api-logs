<?php

namespace Prahsys\ApiLogs\Redactors;

class HipaaRedactor extends DotNotationRedactor
{
    public function __construct(array $additionalPaths = [], string $replacement = '[REDACTED]')
    {
        $hipaaPaths = [
            'patient_id',
            'patientId',
            'medical_record_number',
            'medicalRecordNumber',
            'mrn',
            'diagnosis',
            'condition',
            'medication',
            'treatment',
            'procedure',
            'lab_results',
            'labResults',
            'test_results',
            'testResults',
            'health_plan_id',
            'healthPlanId',
            'member_id',
            'memberId',
            'insurance_id',
            'insuranceId',
            'provider_id',
            'providerId',
            'npi',
            'national_provider_identifier',
            'medical_data',
            'medicalData',
            'health_data',
            'healthData',
            'phi',
            'protected_health_information',
            'patient.id',
            'patient.mrn',
            'patient.diagnosis',
            'patient.medication',
            'medical.patient_id',
            'medical.diagnosis',
            'medical.treatment',
            'health.patient_id',
            'health.condition',
            'patients.*.id',
            'patients.*.mrn',
            'patients.*.diagnosis',
            'medical_records.*.patient_id',
            'medical_records.*.diagnosis',
        ];

        parent::__construct(
            array_merge($hipaaPaths, $additionalPaths),
            $replacement
        );
    }
}
