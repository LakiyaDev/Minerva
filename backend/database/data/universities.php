<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Approved universities
|--------------------------------------------------------------------------
|
| The inter-university eligibility lookup (schema v1.1 §4.1.9, decision D1).
| Rows are never typed free-form by users, so spelling stays canonical.
|
| This is the controlled list consumed by UniversitySeeder. The organising
| committee owns it: add or retire entries here, then re-run the seeder.
| Retiring a university means setting `is_active` to false — rows that are
| still referenced by a user must never be deleted (`ON DELETE RESTRICT`).
|
*/

return [
    ['name' => 'University of Sri Jayewardenepura', 'short_name' => 'USJ'],
    ['name' => 'University of Colombo', 'short_name' => 'UOC'],
    ['name' => 'University of Peradeniya', 'short_name' => 'UOP'],
    ['name' => 'University of Kelaniya', 'short_name' => 'UOK'],
    ['name' => 'University of Moratuwa', 'short_name' => 'UOM'],
    ['name' => 'University of Ruhuna', 'short_name' => 'UOR'],
    ['name' => 'University of Jaffna', 'short_name' => 'UOJ'],
    ['name' => 'University of Vavuniya', 'short_name' => 'UOV'],
    ['name' => 'Sabaragamuwa University of Sri Lanka', 'short_name' => 'SUSL'],
    ['name' => 'Wayamba University of Sri Lanka', 'short_name' => 'WUSL'],
    ['name' => 'Rajarata University of Sri Lanka', 'short_name' => 'RUSL'],
    ['name' => 'South Eastern University of Sri Lanka', 'short_name' => 'SEUSL'],
    ['name' => 'Eastern University, Sri Lanka', 'short_name' => 'EUSL'],
    ['name' => 'Uva Wellassa University', 'short_name' => 'UWU'],
    ['name' => 'University of the Visual and Performing Arts', 'short_name' => 'UVPA'],
    ['name' => 'Open University of Sri Lanka', 'short_name' => 'OUSL'],
    ['name' => 'Sri Lanka Institute of Information Technology', 'short_name' => 'SLIIT'],
];
