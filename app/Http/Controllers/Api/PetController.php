<?php

namespace App\Http\Controllers\Api;

use App\Models\Pet;
use App\Models\PetOwner;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePetRequest;
use App\Http\Requests\UpdatePetRequest;
use App\Http\Resources\PetResource;
use App\Models\Admission;
use App\Models\DewormingLog;
use App\Models\Diagnosis;
use App\Models\Medication;
use App\Models\PetCondition;
use App\Models\ServicesAvailed;
use App\Models\TestResult;
use App\Models\Treatment;
use App\Models\VaccinationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class PetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        $pets = Pet::query()->orderBy('name', 'asc')->paginate(50);

        if ($pets->isEmpty()) {
            return response()->json(['message' => 'No pet records found.'], 404);
        }

        return PetResource::collection($pets);
    }

    public function countPetownerPets($id)
    {
        $pets = Pet::where('petowner_id', $id)->count();
        return response()->json(['data' => $pets]);
    }

    public function searchPet($name)
    {
        try {
            $sanitized_name = trim($name); // Trim whitespace from the input

            // Perform search
            $pets = Pet::where('name', 'like', "%{$sanitized_name}%")
                ->get();

            // Check if any results are found
            if ($pets->isEmpty()) {
                return response()->json(['message' => 'No pets found.'], 404);
            }

            // Return the resource collection
            return PetResource::collection($pets);
        } catch (\Exception $e) {
            // Handle exceptions or errors that may occur during the query
            return response()->json(['message' => 'An error occurred while searching for pets.'], 500);
        }
    }

    public function searchPetbyPetowner($id, $name)
    {
        try {
            $sanitized_name = trim($name); // Trim whitespace from the input

            // Perform search
            $pets = Pet::where('name', 'like', "%{$sanitized_name}%")
                ->where('petowner_id', $id)
                ->get();

            // Check if any results are found
            if ($pets->isEmpty()) {
                return response()->json(['message' => 'No pets found.'], 404);
            }

            // Return the resource collection
            return PetResource::collection($pets);
        } catch (\Exception $e) {
            // Handle exceptions or errors that may occur during the query
            return response()->json(['message' => 'An error occurred while searching for pets.'], 500);
        }
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePetRequest $request, $id)
    {
        $petOwner = PetOwner::findOrFail($id);

        $data = $request->validated(); //get the data

        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $name = time() . '.' . $file->getClientOriginalExtension();
            $name_path = $file->move('storage/pet-photos/', $name);

            $data['photo'] = $name_path;
        }
        
        $data['petowner_id'] = $petOwner->id;

        $pet = Pet::create($data); //create pet
        return new PetResource($pet, 201);
    }

    public function uploadImage(Request $request, $id)
    {
        // Validate request data
        $validatedData = $request->validate([
            'photo' => 'required|file|mimes:jpeg,png,gif,svg|max:2048', // Adjust the max file size as needed
        ]);

        if (!$request->hasFile('photo')) {
            return response()->json(["message" => "Please select an image"], 400);
        }

        // $file = $request->file('photo');
        $file = $validatedData['photo'];
        // Ensure the file is valid
        if (!$file->isValid()) {
            return response()->json(["message" => "Invalid file"], 400);
        }

        $name = time() . '.' . $file->getClientOriginalExtension();
        $filePath = $file->move('storage/pet-photos/', $name);

        $pet = Pet::findOrFail($id); // Adjust this according to your model and input data

        // Delete the previous image if it exists
        if ($pet->photo) {
            $previousImagePath = public_path($pet->photo);

            if (File::exists($previousImagePath)) {
                File::delete($previousImagePath);
            }
        }

        // Update the pet's photo field with the new image path
        $pet->photo = $filePath;

        // Save the updated pet details
        $pet->save();

        return response()->json(['success' => 'Image uploaded successfully']);
    }

    /**
     * Display the specified resource.
     */
    public function show(Pet $pet)
    {
        return new PetResource($pet);
    }

    public function getPetOwnersPet($ownerId)
    {

        $pets = Pet::where('petowner_id', $ownerId)->get();

        if ($pets->isEmpty()) {
            return response()->json(['message' => 'No pet records found.'], 404);
        }
        return PetResource::collection($pets);
    }

    public function archive($id)
    {
        $pet = Pet::findOrFail($id);
        DewormingLog::where('pet_id', $pet->id)->delete();
        VaccinationLog::where('pet_id', $pet->id)->delete();
        Diagnosis::where('pet_id', $pet->id)->delete();
        Admission::where('pet_id', $pet->id)->delete();
        TestResult::where('pet_id', $pet->id)->delete();
        $treatments = Treatment::where('pet_id', $pet->id)->get();
        if ($treatments->isNotEmpty()) {
            foreach ($treatments as $treatment) {
                PetCondition::where('treatment_id', $treatment->id)->delete();
                Medication::where('treatment_id', $treatment->id)->delete();
                $treatment->delete();
            }
        }
        ServicesAvailed::where('pet_id', $pet->id)->delete();
        $pet->delete();
        return response("Pet was archived.");
    }


    public function archivelist()
    {

        $pets = Pet::onlyTrashed()->orderBy('id', 'desc')->get();

        if ($pets->isEmpty()) {
            return response()->json(['message' => 'No pet archives found.'], 404);
        }

        return PetResource::collection($pets);
    }

    public function restore($id)
    {
        $pet = Pet::withTrashed()->findOrFail($id);
        DewormingLog::where('pet_id', $pet->id)->restore();
        VaccinationLog::where('pet_id', $pet->id)->restore();
        Diagnosis::where('pet_id', $pet->id)->restore();
        Admission::where('pet_id', $pet->id)->restore();
        TestResult::where('pet_id', $pet->id)->restore();
        $treatments = Treatment::where('pet_id', $pet->id)->get();
        if ($treatments->isNotEmpty()) {
            foreach ($treatments as $treatment) {
                PetCondition::where('treatment_id', $treatment->id)->restore();
                Medication::where('treatment_id', $treatment->id)->restore();
                $treatment->restore();
            }
        }
        ServicesAvailed::where('pet_id', $pet->id)->restore();
        $pet->restore();
        return response("Pet restored successfully");
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePetRequest $request, Pet $pet)
    {
        $data = $request->validated();
        $pet->update($data);

        return new PetResource($pet);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Pet $pet, $id)
    {
        $pet = Pet::withTrashed()->findOrFail($id);
        DewormingLog::where('pet_id', $pet->id)->forceDelete();
        VaccinationLog::where('pet_id', $pet->id)->forceDelete();
        Diagnosis::where('pet_id', $pet->id)->forceDelete();
        Admission::where('pet_id', $pet->id)->forceDelete();
        TestResult::where('pet_id', $pet->id)->forceDelete();
        $treatments = Treatment::where('pet_id', $pet->id)->get();
        if ($treatments->isNotEmpty()) {
            foreach ($treatments as $treatment) {
                PetCondition::where('treatment_id', $treatment->id)->forceDelete();
                Medication::where('treatment_id', $treatment->id)->forceDelete();
                $treatment->forceDelete();
            }
        }
        ServicesAvailed::where('pet_id', $pet->id)->forceDelete();
        $pet->forceDelete();
        return response("Permanently Deleted", 201);
    }
}
