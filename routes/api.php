<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', 'Api\UserController@user');

Route::middleware('cors')->post('/v1/user/login', 'Api\UserController@login');
Route::middleware('cors')->post('/v1/user/register', 'Api\UserController@register');
Route::middleware('cors')->post('/v1/user/change-password', 'Api\UserController@change_password');
Route::middleware('cors')->get('/v1/user/forgot-password', 'Api\UserController@forgot_password');

Route::middleware('cors')->get('/v1/report/check-ins', 'Api\ReportController@check_ins');
Route::middleware('cors')->get('/v1/report/tasks-by-date', 'Api\ReportController@tasks_by_date');
Route::middleware('cors')->get('/v1/task/notify-expiring', 'Api\TaskController@notify_expiring');

Route::middleware('cors')->get('/v1/client', 'Api\ClientController@index');

Route::middleware('cors')->get('/v1/user-type', 'Api\UserTypeController@index');
Route::middleware('cors')->post('/v1/contact-us/create', 'Api\ContactUsController@create');


Route::middleware(['cors', 'auth:api'])->group(function () {
  Route::get('/v1/user', 'Api\UserController@index');
  Route::get('/v1/user/logout', 'Api\UserController@logout');
  Route::get('/v1/user/read-all-notification', 'Api\UserController@read_all_notification');
  Route::get('/v1/user/{id}', 'Api\UserController@retrieve');
  Route::get('/v1/user/{id}/pin-label', 'Api\UserController@pin_label');
  Route::get('/v1/user/{id}/resend-code', 'Api\UserController@resend_code');
  
  Route::post('/v1/user/create', 'Api\UserController@create');
  Route::post('/v2/user/create', 'Api\UserController@create_with_subscription_type');
  Route::post('/v1/user/{id}/update', 'Api\UserController@update');
  Route::post('/v2/user/{id}/update', 'Api\UserController@update_with_subscription_type');
  Route::post('/v1/user/{id}/toggle-status', 'Api\UserController@toggle_status');
  Route::post('/v1/user/{id}/update-fcm', 'Api\UserController@update_fcm');
  Route::post('/v1/user/{id}/activate', 'Api\UserController@activate');

  Route::get('/v1/user/{id}/notifications', 'Api\UserController@notifications');
  Route::get('/v1/user/{id}/task-stats', 'Api\UserController@task_stats');
  Route::post('/v1/user/read-notification', 'Api\UserController@read_notification');
  Route::post('/user/update-last-notification', 'Api\UserController@update_last_notification');

  Route::post('/v1/user-type/create', 'Api\UserTypeController@create');
  Route::post('/v1/user-type/{id}/update', 'Api\UserTypeController@update');
  Route::post('/v1/user-type/{id}/toggle-status', 'Api\UserTypeController@toggle_status');
  Route::get('/v1/user-type/{id}', 'Api\UserTypeController@retrieve');

  Route::get('/v1/subscription-type', 'Api\SubscriptionTypeController@index');
  Route::post('/v1/subscription-type/create', 'Api\SubscriptionTypeController@create');
  Route::post('/v1/subscription-type/{id}/update', 'Api\SubscriptionTypeController@update');
  Route::post('/v1/subscription-type/{id}/toggle-status', 'Api\SubscriptionTypeController@toggle_status');
  Route::get('/v1/subscription-type/{id}', 'Api\SubscriptionTypeController@retrieve');
  
  Route::get('/v1/client/latest-locations', 'Api\ClientController@latest_locations');
  Route::post('/v1/client/check-in', 'Api\ClientController@check_in');
  Route::post('/v1/client/create', 'Api\ClientController@create');
  Route::post('/v1/client/create-by-user', 'Api\ClientController@create_by_user');
  Route::get('/v1/client/staff-check-in', 'Api\ClientController@staff_check_in');
  Route::get('/v1/client/staff-check-in-single/{id}', 'Api\ClientController@staff_check_in_single');
  Route::post('/v1/client/{id}/update', 'Api\ClientController@update');
  Route::post('/v1/client/{id}/toggle-status', 'Api\ClientController@toggle_status');
  Route::post('/v1/client/{id}/assign-pin-bucket', 'Api\ClientController@assign_pin_bucket');
  Route::get('/v1/client/{id}', 'Api\ClientController@retrieve');

  Route::get('/v1/site-visit', 'Api\SiteVisitController@index');
  Route::get('/v1/site-visit/staff-site-visit', 'Api\SiteVisitController@staff_visits');
  Route::post('/v1/site-visit/create', 'Api\SiteVisitController@create');
  Route::get('/v1/site-visit/{id}', 'Api\SiteVisitController@retrieve');

  Route::get('/v1/incident-type', 'Api\IncidentTypeController@index');
  Route::post('/v1/incident-type/create', 'Api\IncidentTypeController@create');
  Route::post('/v1/incident-type/{id}/update', 'Api\IncidentTypeController@update');
  Route::post('/v1/incident-type/{id}/toggle-status', 'Api\IncidentTypeController@toggle_status');
  Route::get('/v1/incident-type/{id}', 'Api\IncidentTypeController@retrieve');

  Route::get('/v1/incident', 'Api\IncidentController@index');
  Route::get('/v1/incident/staff-incident-reports', 'Api\IncidentController@staff_incident_reports');
  Route::post('/v1/incident/create', 'Api\IncidentController@create');
  Route::get('/v1/incident/{id}', 'Api\IncidentController@retrieve');

  Route::get('/v1/module', 'Api\ModuleController@index');
  Route::post('/v1/module/create', 'Api\ModuleController@create');
  Route::post('/v1/module/{id}/update', 'Api\ModuleController@update');
  Route::post('/v1/module/{id}/delete', 'Api\ModuleController@delete');
  Route::get('/v1/module/{id}', 'Api\ModuleController@retrieve');

  Route::get('/v1/team', 'Api\TeamController@index');
  Route::get('/v1/team-by-user', 'Api\TeamController@get_teams_by_user');
  Route::post('/v1/team/create', 'Api\TeamController@create');
  Route::post('/v1/team/create-by-user', 'Api\TeamController@create_by_user');
  Route::post('/v1/team/{id}/update', 'Api\TeamController@update');
  Route::post('/v1/team/{id}/toggle-status', 'Api\TeamController@toggle_status');
  Route::post('/v1/team/{id}/confirm-add-member', 'Api\TeamController@confirm_add_member');
  Route::post('/v1/team/{id}/add-member', 'Api\TeamController@add_member');
  Route::post('/v1/team/{id}/update-member/{user_id}', 'Api\TeamController@update_member');
  Route::get('/v1/team/{id}', 'Api\TeamController@retrieve');
  Route::get('/v1/team/{id}/dashboard', 'Api\TeamController@dashboard');
  Route::get('/v1/team/{id}/members', 'Api\TeamController@get_members');
  Route::get('/v1/team/{id}/latest-locations', 'Api\TeamController@get_latest_locations');

  Route::get('/v1/task', 'Api\TaskController@index');

  Route::get('/v1/task/templates', 'Api\TaskController@get_templates');
  Route::get('/v1/task-template/{id}', 'Api\TaskController@get_template_by_id');
  Route::post('/v1/task-template/{id}/update', 'Api\TaskController@update_template');
  Route::post('/v1/task-template/{id}/delete', 'Api\TaskController@delete_template');
  
  Route::post('/v1/task/create', 'Api\TaskController@create');
  Route::post('/v2/task/create', 'Api\TaskController@create_with_notif'); // with notif
  Route::post('/v3/task/create', 'Api\TaskController@create_with_template'); // with template

  Route::post('/v1/task/{id}/update', 'Api\TaskController@update');
  Route::post('/v2/task/{id}/update', 'Api\TaskController@update_with_ref_no'); // with reference number

  Route::get('/v1/task/{id}', 'Api\TaskController@retrieve');
  Route::get('/v1/task/{id}/with-history', 'Api\TaskController@retrieve_with_history');
  Route::get('/v1/task/{id}/comments', 'Api\TaskController@get_comments');
  Route::get('/v1/task-detail/{id}/history', 'Api\TaskController@task_detail_history');
  
  Route::post('/v1/task/{id}/approve', 'Api\TaskController@approve');
  Route::post('/v2/task/{id}/approve', 'Api\TaskController@approve_with_notif'); // with notif
  
  Route::post('/v1/task-detail/{id}/toggle-complete', 'Api\TaskController@task_detail_toggle_complete');
  Route::post('/v1/task-detail/{id}/rate-task', 'Api\TaskController@rate_task');
  Route::post('/v1/task-detail/{id}/extend', 'Api\TaskController@extend');
  Route::post('/v2/task-detail/{id}/extend', 'Api\TaskController@extend_reset_notify');

  Route::post('/v1/task/{id}/comment', 'Api\TaskController@comment');
  Route::post('/v2/task/{id}/comment', 'Api\TaskController@comment_with_notif');
  Route::post('/v1/task/{id}/attach', 'Api\TaskController@attach');
  Route::post('/v1/task/{id}/remove-attachment', 'Api\TaskController@remove_attachment');
  Route::post('/v1/task/{id}/toggle-lock-attachment', 'Api\TaskController@toggle_lock_attachment');
  
  Route::post('/v1/task/{id}/for-verification', 'Api\TaskController@for_verification');
  Route::post('/v2/task/{id}/for-verification', 'Api\TaskController@for_verification_with_notif');
  
  Route::post('/v1/task/{id}/verify-as-complete', 'Api\TaskController@verify_as_complete');
  Route::post('/v2/task/{id}/verify-as-complete', 'Api\TaskController@verify_as_complete_with_notif');

  Route::post('/v1/task/{id}/delete', 'Api\TaskController@delete');

  
  Route::get('/v1/collections/user-dropdowns', 'Api\CollectionsController@user_dropdowns');
  Route::get('/v2/collections/user-dropdowns', 'Api\CollectionsController@user_dropdowns_without_teams');

  Route::get('/v1/report/manager-dashboard', 'Api\ReportController@manager_dashboard');
  Route::get('/v2/report/manager-dashboard', 'Api\ReportController@manager_dashboard_with_date');
  Route::get('/v1/report/md-dashboard', 'Api\ReportController@managing_director_dashboard');
  Route::get('/v2/report/md-dashboard', 'Api\ReportController@managing_director_dashboard_with_date');

});


