<?php

namespace App\Imports;

use App\Models\Contact;
use App\Models\ContactField;
use App\Models\ContactGroup;
use App\Services\PhoneService;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class ContactsImport extends \PhpOffice\PhpSpreadsheet\Cell\StringValueBinder implements ToModel, WithHeadingRow, WithCustomValueBinder, WithChunkReading
{
    protected $totalImports = 0;
    protected $successfulImports = 0;
    protected $failedImports = [];
    protected $failedImportsDueToFormat = 0;
    protected $failedImportsDueToDuplicates = 0;
    protected $failedImportsDueToLimit = 0;

    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        try {
            $this->totalImports++;

            $phoneNumberValue = $row['phone'];

            if (!str_starts_with($phoneNumberValue, '+')) {
                $phoneNumberValue = '+' . $phoneNumberValue;
            }

            // Sequential Validation: First check the first_name field
            $validator = Validator::make($row, [
                'first_name' => 'required', // Make first_name required
            ]);

            // If first_name fails, stop further validation and return the error
            if ($validator->fails()) {
                $this->failedImports[] = [
                    'row' => $row['phone'],
                    'error' => __('First name required!')
                ];
                $this->failedImportsDueToFormat++;
                return null;
            }

            // If first_name passes, proceed to validate the phone field
            $validator = Validator::make($row, [
                'phone' => 'required', // Make phone required
            ]);

            // If phone is invalid, add the error and return
            if ($validator->fails()) {
                $this->failedImports[] = [
                    'row' => $row['phone'],
                    'error' => __('Phone number required!')
                ];
                $this->failedImportsDueToFormat++;
                return null;
            }

            // Now validate the phone number format
            if (!PhoneService::isValid($phoneNumberValue)) {
                $this->failedImports[] = [
                    'row' => $row['phone'],
                    'error' => __('Invalid format!')
                ];
                $this->failedImportsDueToFormat++;
                return null;
            }

            // Check if the phone number already exists in the database
            $formattedPhone = PhoneService::getE164Format($phoneNumberValue);
            if (Contact::where('organization_id', session()->get('current_organization'))
                    ->where('phone', $formattedPhone)
                    ->whereNull('deleted_at')
                    ->exists()) {
                $this->failedImports[] = [
                    'row' => $row['phone'],
                    'error' => __('Duplicate phone number!')
                ];
                $this->failedImportsDueToDuplicates++;
                return null;
            }

            // Fetch dynamic fields from contact_fields table
            $organizationId = session()->get('current_organization');
            if (app(\App\Services\SubscriptionFeatureUsageService::class)->isReached((int) $organizationId, 'contacts_limit')) {
                $this->failedImports[] = [
                    'row' => $row['phone'],
                    'error' => __('Contact limit reached!')
                ];
                $this->failedImportsDueToLimit++;
                return null;
            }

            // Fetch dynamic fields from contact_fields table
            $contactFields = ContactField::where('organization_id', $organizationId)->pluck('name')->toArray();

            $metadata = [];

            foreach ($contactFields as $field) {
                // Normalize field name to match PhpSpreadsheet's header normalization
                // PhpSpreadsheet converts headers to lowercase and replaces spaces with underscores
                $normalizedField = strtolower(str_replace(' ', '_', $field));

                // Check for the normalized field name (with underscore)
                if (isset($row[$normalizedField])) {
                    $metadata[$field] = $row[$normalizedField];
                } else {
                    // Also check for lowercase with space (fallback for edge cases)
                    $normalizedFieldWithSpace = strtolower($field);
                    if (isset($row[$normalizedFieldWithSpace])) {
                        $metadata[$field] = $row[$normalizedFieldWithSpace];
                    }
                }
            }

            $contact =  Contact::create([
                'organization_id'  => $organizationId,
                'first_name'  => $row['first_name'],
                'last_name'   => $row['last_name'],
                'phone'       => PhoneService::getE164Format($phoneNumberValue), 
                'email'       => $row['email'],
                'address'     => json_encode([
                    'street'  => $row['street'] ?? null,
                    'city'    => $row['city'] ?? null,
                    'state'   => $row['state'] ?? null,
                    'zip'     => $row['zip'] ?? null,
                    'country' => $row['country'] ?? null
                ]),
                'metadata'    => !empty($metadata) ? json_encode($metadata) : null, 
                'created_by'  => auth()->user()->id,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            if($contact){
                $this->successfulImports++;

                // Process contact groups (if provided)
                if (!empty($row['group_name'])) {
                    $groupNames = explode('|', $row['group_name'] ?? ''); // Split by comma
                    $groupNames = array_map('trim', $groupNames); // Trim whitespace

                    foreach ($groupNames as $groupName) {
                        $group = ContactGroup::firstOrCreate([
                            'organization_id' => $organizationId,
                            'name'            => $groupName,
                            'deleted_at'      => null
                        ], [
                            'created_by' => auth()->user()->id,
                        ]);

                        // Attach contact to the group via pivot table
                        $contact->contactGroups()->attach($group->id);
                    }
                }

                return $contact;
            }
        } catch (\Exception $e) {
            $this->failedImports[] = [
                'row' => $row['phone'],
                'error' => __('Invalid format!')
            ];
            $this->failedImportsDueToFormat++;

            return null;
        }
    }

    public function getFailedImportsDueToFormat()
    {
        return $this->failedImportsDueToFormat;
    }

    public function getFailedImportsDueToDuplicatesCount()
    {
        return $this->failedImportsDueToDuplicates;
    }

    public function getFailedImportsDueToLimit()
    {
        return $this->failedImportsDueToLimit;
    }

    public function getSuccessfulImports()
    {
        return $this->successfulImports;
    }

    public function getFailedImports()
    {
        return $this->failedImports;
    }

    public function getTotalImportsCount()
    {
        return $this->totalImports;
    }

    public function chunkSize(): int
    {
        return 1000; // Adjust the chunk size as needed
    }
}

