<?php

namespace App\Admin\Controllers;

use App\Models\Crop;
use App\Models\CropVariety;
use App\Models\ImportExportPermit;
use App\Models\Utils;
use Carbon\Carbon;
use Encore\Admin\Auth\Database\Administrator;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Form\NestedForm;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Encore\Admin\Widgets\Table;
use Illuminate\Support\Facades\Auth;

class ImportExportPermitController2 extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Export Permit';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new ImportExportPermit());
        $grid->disableFilter();
        $grid->disableExport(); 
        $grid->model()->where('is_import', '!=', 1);
        


        if (Admin::user()->isRole('basic-user')) {
            $grid->model()->where(
                'administrator_id', '=', Admin::user()->id
            );

            if (!Utils::can_create_export_form()) {
                $grid->disableCreateButton();
            }

            $grid->actions(function ($actions) {
                $status = ((int)(($actions->row['status'])));
                $actions->disableEdit();
                if (
                    $status == 2 ||
                    $status == 5 ||
                    $status == 6
                ) {
                    $actions->disableDelete();
                }
            });
        } else if (Admin::user()->isRole('inspector')) {
            $grid->model()->where('inspector', '=', Admin::user()->id);
            $grid->disableCreateButton();

            $grid->actions(function ($actions) {
                $status = ((int)(($actions->row['status'])));
                $actions->disableDelete();
                if (
                    $status != 2
                ) {
                    $actions->disableEdit();
                }
            });
        } else {
            $grid->disableCreateButton();
        }


        $grid->column('id', __('Id'));
        $grid->column('created_at', __('Created'))
            ->display(function ($item) {
                return Carbon::parse($item)->diffForHumans();
            })->sortable();
        $grid->column('name', __('Name'));
        $grid->column('telephone', __('Telephone'));
        $grid->column('quantiry_of_seed', __('Quantiry of seed'));

        $grid->column('administrator_id', __('Created by'))->display(function ($userId) {
            $u = Administrator::find($userId);
            if (!$u)
                return "-";
            return $u->name;
        })->sortable();

        $grid->column('inspector', __('Inspector'))->display(function ($userId) {
            if (Admin::user()->isRole('basic-user')) {
                return "-";
            }
            $u = Administrator::find($userId);
            if (!$u)
                return "Not assigned";
            return $u->name;
        })->sortable();


        $grid->column('status', __('Status'))->display(function ($status) {
            return Utils::tell_status($status);
        })->sortable();

        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(ImportExportPermit::findOrFail($id));
        $show->panel()
            ->tools(function ($tools) {
                $tools->disableEdit();
                $tools->disableDelete();
            });;

        $show->field('created_at', __('Created'))
            ->as(function ($item) {
                if (!$item) {
                    return "-";
                }
                return Carbon::parse($item)->diffForHumans();
            });
        $show->field('administrator_id', __('Created by id'))
            ->as(function ($userId) {
                $u = Administrator::find($userId);
                if (!$u)
                    return "-";
                return $u->name;
            });

        $show->field('name', __('Name'));
        $show->field('address', __('Address'));
        $show->field('telephone', __('Telephone'));
        $show->field('national_seed_board_reg_num', __('National seed board reg num'));
        $show->field('store_location', __('Store location'));
        $show->field('quantiry_of_seed', __('Quantiry of seed'));
        $show->field('name_address_of_origin', __('Name address of origin'));
        $show->field('dealers_in', __('Crops'))
            ->unescape()
            ->as(function ($item) {
                if (!$this->import_export_permits_has_crops) {
                    return "None";
                }

                $headers = ['Crop', 'Variety', 'Category', 'weight'];
                $rows = array();
                foreach ($this->import_export_permits_has_crops as $key => $val) {
                    $var = CropVariety::firstWhere($val->crop_variety_id);


                    $row['crop'] = $var->crop->name;
                    $row['variety'] = $var->name;
                    $row['ha'] = $val->category;
                    $row['origin'] = $val->weight;
                    $rows[] = $row;
                }

                $table = new Table($headers, $rows);
                return $table;
            });
        $show->field('ista_certificate', __('Ista certificate'));
        $show->field('permit_number', __('Permit number'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {

        $sr4 = Utils::has_valid_sr4();
        if (Admin::user()->isRole('basic-user')) {
            if (!$sr4) {
                admin_error("Alert", "You need to be a registred and approved seed merchant to apply for an import permit.");
                return redirect(admin_url('import-export-permits'));
            }
        }

        $form = new Form(new ImportExportPermit());
        $form->setWidth(8, 4);
        $form->disableCreatingCheck();
        $form->tools(function (Form\Tools $tools) {
            $tools->disableDelete();
            $tools->disableView();
        });

        $form->footer(function ($footer) {
            $footer->disableReset(); 
            $footer->disableViewCheck();
            $footer->disableEditingCheck();
            $footer->disableCreatingCheck();
        });
        



        $user = Auth::user();
        $form->hidden('is_import', __('is_import'))->value(0)->required();

        if ($form->isCreating()) {
            $form->hidden('administrator_id', __('Administrator id'))->value($user->id);
        } else {
            $form->hidden('administrator_id', __('Administrator id'));
        }
        if (Admin::user()->isRole('basic-user')) {
            $form->text('name', __('Name'))->default($user->name)->required();
            $form->text('address', __('Postal Address'))->required();
            $form->text('telephone', __('Phone number'))->required();
            $form->text(
                'national_seed_board_reg_num',
                __('National seed board registration number')
            )
                ->readonly()
                ->value($sr4->seed_board_registration_number);

            $form->text('store_location', __('Location of the store'))->required();
            $form->text(
                'quantiry_of_seed',
                __('Quantity of seed of the same variety held in stock')
            )
                ->help("In METRIC TONNES")
                ->attribute(['type' => 'number'])
                ->required();
            $form->text(
                'name_address_of_origin',
                __('Name and address of destination')
            )
                ->required();
            $form->file('ista_certificate', __('ISTA certificate'))->required();
            $form->file('phytosanitary_certificate', __('Phytosanitary certificate'))->required();


            $form->html('<h3>I/We wish to apply for a license to import seed as indicated below:</h3>');

            $form->hasMany('import_export_permits_has_crops', __('Click on "New" to Add Crop varieties
            '), function (NestedForm $form) {
                $_items = [];
                foreach (CropVariety::all() as $key => $item) {
                    $_items[$item->id] = "CROP: " . $item->crop->name . ", VARIETY: " . $item->name;
                }
                $form->select('crop_variety_id', 'Add Crop Variety')->options($_items)
                    ->required();
                $form->text('category', __('Category'))->required();
                $form->text('weight', __('Weight (in KGs)'))->required();
            });
        }
        if (Admin::user()->isRole('admin')) {
            //$form->file('ista_certificate', __('Ista certificate'))->required();
            $form->text('name', __('Name of applicant'))->default($user->name)->readonly();
            $form->text('telephone', __('Telephone'))->readonly();
            $form->text('address', __('Address'))->readonly();
            $form->text('store_location', __('Store location'))->readonly();
            $form->divider();
            $form->radio('status', __('Status'))
                ->options([
                    '1' => 'Pending',
                    '2' => 'Under inspection',
                ])
                ->required()
                ->when('2', function (Form $form) {
                    $items = Administrator::all();
                    $_items = [];
                    foreach ($items as $key => $item) {
                        if (!Utils::has_role($item, "inspector")) {
                            continue;
                        }
                        $_items[$item->id] = $item->name . " - " . $item->id;
                    }
                    $form->select('inspector', __('Inspector'))
                        ->options($_items)
                        ->help('Please select inspector')
                        ->rules('required');
                })
                ->when('in', [3, 4], function (Form $form) {
                    $form->textarea('status_comment', 'Enter status comment (Remarks)')
                        ->help("Please specify with a comment");
                })
                ->when('in', [5, 6], function (Form $form) {
                    $form->date('valid_from', 'Valid from date?');
                    $form->date('valid_until', 'Valid until date?');
                });
        }



        if (Admin::user()->isRole('inspector')) {

            $form->text('name', __('Name of applicant'))->default($user->name)->readonly();
            $form->text('telephone', __('Telephone'))->readonly();
            $form->text('address', __('Address'))->readonly();
            $form->text('store_location', __('Store location'))->readonly();
            $form->divider();

            $form->radio('status', __('Status'))
                ->options([
                    '3' => 'Halted',
                    '4' => 'Rejected',
                    '5' => 'Accepted', 
                ])
                ->required()
                ->when('2', function (Form $form) {
                    $items = Administrator::all();
                    $_items = [];
                    foreach ($items as $key => $item) {
                        if (!Utils::has_role($item, "inspector")) {
                            continue;
                        }
                        $_items[$item->id] = $item->name . " - " . $item->id;
                    }
                    $form->select('inspector', __('Inspector'))
                        ->options($_items)
                        ->readonly()
                        ->help('Please select inspector')
                        ->rules('required');
                })
                ->when('in', [3, 4], function (Form $form) {
                    $form->textarea('status_comment', 'Enter status comment (Remarks)')
                        ->help("Please specify with a comment");
                })
                ->when('in', [5, 6], function (Form $form) {
                    $form->text('permit_number', __('Permit number'))
                        ->help("Please Enter Permit number")
                        ->default(rand(10000, 1000000));
                    $form->date('valid_from', 'Valid from date?');
                    $form->date('valid_until', 'Valid until date?');
                });

 
        }



        return $form;
    }
}
