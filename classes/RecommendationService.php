<?php
class RecommendationService {
    /**
     * Get recommended laboratory tests with prices
     */
    public function getLabTests($specialty) {
        $map = [
            'General Physician' => [
                ['name' => 'Complete Blood Count (CBC)', 'price' => 500],
                ['name' => 'Urinalysis', 'price' => 300],
                ['name' => 'Blood Chemistry', 'price' => 800],
                ['name' => 'Fasting Blood Sugar (FBS)', 'price' => 400],
                ['name' => 'Lipid Profile', 'price' => 600],
            ],
            'Cardiologist' => [
                ['name' => 'Electrocardiogram (ECG)', 'price' => 1200],
                ['name' => 'Lipid Profile', 'price' => 600],
                ['name' => 'Cardiac Troponin Test', 'price' => 1500],
                ['name' => 'Creatine Kinase-MB (CK-MB)', 'price' => 1000],
                ['name' => 'Echocardiogram', 'price' => 3000],
            ],
            'Radiologist' => [
                ['name' => 'Chest X-ray', 'price' => 800],
                ['name' => 'Abdominal Ultrasound', 'price' => 2000],
                ['name' => 'CT Scan', 'price' => 5000],
                ['name' => 'MRI Scan', 'price' => 8000],
                ['name' => 'Bone X-ray', 'price' => 1000],
            ]
        ];
        return $map[$specialty] ?? [];
    }

    /**
     * Get recommended medications for a given specialty (with dosage and defaults)
     */
    public function getMedications($specialty) {
        $map = [
            'General Physician' => [
                ['name' => 'Paracetamol', 'dosage' => '500 mg', 'frequency' => 'Every 6 hours', 'duration' => '5 days'],
                ['name' => 'Amoxicillin', 'dosage' => '500 mg', 'frequency' => 'Every 8 hours', 'duration' => '7 days'],
                ['name' => 'Ibuprofen', 'dosage' => '400 mg', 'frequency' => 'Every 8 hours', 'duration' => '5 days'],
                ['name' => 'Cetirizine', 'dosage' => '10 mg', 'frequency' => 'Once daily', 'duration' => '7 days'],
                ['name' => 'Omeprazole', 'dosage' => '20 mg', 'frequency' => 'Once daily', 'duration' => '14 days'],
            ],
            'Cardiologist' => [
                ['name' => 'Amlodipine', 'dosage' => '5 mg', 'frequency' => 'Once daily', 'duration' => '30 days'],
                ['name' => 'Losartan', 'dosage' => '50 mg', 'frequency' => 'Once daily', 'duration' => '30 days'],
                ['name' => 'Atorvastatin', 'dosage' => '20 mg', 'frequency' => 'Once daily', 'duration' => '30 days'],
                ['name' => 'Aspirin', 'dosage' => '81 mg', 'frequency' => 'Once daily', 'duration' => '30 days'],
                ['name' => 'Metoprolol', 'dosage' => '50 mg', 'frequency' => 'Twice daily', 'duration' => '30 days'],
            ],
            'Radiologist' => [
                ['name' => 'Contrast Media', 'dosage' => 'As indicated', 'frequency' => 'Per procedure', 'duration' => '1 day'],
                ['name' => 'Paracetamol', 'dosage' => '500 mg', 'frequency' => 'Every 6 hours', 'duration' => '3 days'],
                ['name' => 'Ibuprofen', 'dosage' => '400 mg', 'frequency' => 'Every 8 hours', 'duration' => '3 days'],
                ['name' => 'Cetirizine', 'dosage' => '10 mg', 'frequency' => 'Once daily', 'duration' => '3 days'],
                ['name' => 'Prednisone', 'dosage' => '20 mg', 'frequency' => 'Once daily', 'duration' => '3 days'],
            ]
        ];
        return $map[$specialty] ?? [];
    }

    public function getNote($specialty) {
        if ($specialty === 'Radiologist') {
            return 'Note: Medication recommendations for radiology procedures should follow the referring physician\'s clinical judgment.';
        }
        return '';
    }
}
?>