<?php

namespace App\Http\Controllers\Api;

use App\Models\Crop;
use App\Models\CropVariety;
use Encore\Admin\Controllers\AdminController; 
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;
use App\Traits\ApiResponser;
use App\Models\ImportExportPermit;
use App\Models\ImportExportPermitsHasCrops;
use App\Models\Utils;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;


class ImportPermitApiController extends AdminController
{
    use ApiResponser;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function import_permits_list()
    {
        /*  ---attributes---
        */
        $user = auth()->user();
        //$query = DB::table('import_export_permits')->where('administrator_id', $user->id)->get();

        $query = DB::table('import_export_permits')
        ->where('administrator_id', $user->id)
        ->where('is_import', '==', 1)
        ->get();

        // $query = ImportExportPermit::all();
        
        return $this->successResponse($query, $message="Import permits"); 
    } 


    
    // create new sr4 form
    public function import_permits_create(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();

        $data = $request->only(
            // 'name',
            'address',
            'telephone',
            'type',
            'store_location',
            'quantiry_of_seed',
            'name_address_of_origin',
            'ista_certificate', 
            'phytosanitary_certificate',
            'crop_category',
        );

        $post_data = Validator::make($data, [
            'address' => 'required',
            'telephone' => 'required',
            'type' => 'required',
            'store_location' => 'required',
            'quantiry_of_seed' => 'required',
            'name_address_of_origin' => 'required', 
        ]);

        /* $f =  new ImportExportPermit();
        $f->address = $r->address;
        $f->address = $r->address;
        $f->address = $r->address;
        $f->address = $r->address;

        if($f->save()){
            ///
        }else{
            ///
        } */
        
 
        if ($post_data->fails()) {
            return $this->errorResponse("Permit validation failed", 200); 
        }

 

        $form = ImportExportPermit::create([
            'administrator_id' => $user->id, 
            'name' => $user->name,
            'address' => $request->input('address'),
            'telephone' => $request->input('telephone'),
            'type' => $request->input('type'),
            'store_location' => $request->input('store_location'),
            'quantiry_of_seed' => $request->input('quantiry_of_seed'),
            'name_address_of_origin' => $request->input('name_address_of_origin'),
            'ista_certificate' => $request->input('ista_certificate'), 
            'phytosanitary_certificate' => $request->input('phytosanitary_certificate'),
            'crop_category' => $request->input('crop_category'),
            'is_import' => (int) ($request->input('is_import')),
        ]);

        $import_export_permits_has_crops = json_decode($request->input('import_export_permits_has_crops'));
        if($import_export_permits_has_crops!=null){
            if(is_array($import_export_permits_has_crops)){ 
                foreach ($import_export_permits_has_crops as $key => $value) {
                    $crop_variety_id = ((int)($value));
                    $crop_var = CropVariety::find($crop_variety_id);
                    if($crop_var == null){
                        continue;
                    }
                    $ImportExportPermitsHasCrop = new ImportExportPermitsHasCrops();
                    $ImportExportPermitsHasCrop->import_export_permit_id = $form->id;
                    $ImportExportPermitsHasCrop->crop_variety_id = $crop_variety_id;
                    $ImportExportPermitsHasCrop->save();

                }
            }
        } 
        
        // Form created, return success response
        if(((int)($request->input('is_import'))) == 1){
            $msg = "Import permit submitted successfully!";
        }else{
            $msg = "Export permit submitted successfully!";
        }
        return $this->successResponse($form, $msg, 201); 
    }


    
    // delete import permit form
    public function import_permit_delete(Request $request): \Illuminate\Http\JsonResponse
    {
        $user_id = auth()->user()->id;
        $id = ((int)($request->input('id')));
        $item = ImportExportPermit::where('is_import', '=>', 1)->find($id);
        if ($item == null) {
            return $this->errorResponse("Failed to delete  because the item was not found.", 200);
        }
        if ($item->administrator_id != $user_id) {
            return $this->errorResponse("You cannot delete an item that does not belong to you.", 200);
        }
        if (!Utils::can_be_deleted_by_user($item->status)) {
            return $this->errorResponse("Item at this stage cannot be deleted.", 200);
        }
        ImportExportPermit::where('id', $id)->delete();
        return $this->successResponse($item, "Item deleted successfully!", 201);
    }
}
