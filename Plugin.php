<?php namespace Artistro08\Profiles;

use Backend;
use Event;
use Log;
use System\Classes\PluginBase;
use RainLab\User\Models\User;
use Tailor\Models\EntryRecord;

/**
 * Plugin Information File
 */
class Plugin extends PluginBase
{
    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'        => 'Profiles',
            'description' => 'Extends the User plugin to have relations to the Tailor Profiles in the backend.',
            'author'      => 'Artistro08',
            'icon'        => 'icon-user'
        ];
    }

    /**
     * Register method, called when the plugin is first registered.
     *
     * @return void
     */
    public function register()
    {

    }

    /**
     * Boot method, called right before the request route.
     *
     * @return void
     */
    public function boot()
    {

        // Deactivate the related record in Tailor if the user deactivates their account
        Event::listen('rainlab.user.deactivate', function($user) {
            $profile             = EntryRecord::inSection('Content\Profile')->where('user_id', $user->id)->first();
            $profile->is_enabled = 0;
            $profile->save();
        });

        // Reactivate the related record in Tailor if the user reactivates their account
        Event::listen('rainlab.user.reactivate', function($user) {
            $profile             = EntryRecord::inSection('Content\Profile')->where('user_id', $user->id)->first();
            $profile->is_enabled = 1;
            $profile->save();
        });

        // Tailor User Record Management
        User::extend(function($user) {

            // Create record if the user creates an account
            $user->bindEvent('model.afterSave', function() use ($user) {
                $existingProfile = EntryRecord::inSection('Content\Profile')->where('user_id', $user->id)->first();


                if($user->is_activated && !empty($existingProfile)) {

                    // Failsafe if the user's profile record exists
                    $existingProfile->is_enabled = 1;
                    $existingProfile->save();

                    if($user->name != $existingProfile->user_name) {
                        // Update the profile name they change it.
                        $existingProfile->user_name  = $user->name;
                        $existingProfile->title      = $user->name;
                        $existingProfile->is_enabled = 1;
                        $existingProfile->save();
                    }

                    if($user->username != $existingProfile->slug) {
                        // Update the username they change it.
                        $existingProfile->slug       = $user->username;
                        $existingProfile->is_enabled = 1;
                        $existingProfile->save();
                    }

                } elseif($user->is_activated && empty($existingProfile)) {

                    // Creates the record and relation
                    $profile = EntryRecord::inSection('Content\Profile');

                    $profile->is_enabled          = 1;
                    $profile->email_notifications = 1;
                    $profile->notify_on_follow    = 1;
                    $profile->notify_on_like      = 1;
                    $profile->title               = $user->name;
                    $profile->user_name           = $user->name;
                    $profile->user_id             = $user->id;
                    $profile->meta_title          = $user->name;
                    $profile->followers           = [];
                    $profile->notifications       = [];
                    $profile->facebook            = '';
                    $profile->twitter             = '';
                    $profile->instagram           = '';
                    $profile->linkedin            = '';
                    $profile->pinterest           = '';
                    $profile->cashapp             = '';
                    $profile->venmo               = '';
                    $profile->whatsapp            = '';

                    $profile->save();

                }
            });

            // Deletes the record if the user is deleted in the backend
            $user->bindEvent('model.afterDelete', function() use ($user) {

                if(!$user->isSoftDelete()) {

                    $profile = EntryRecord::inSection('Content\Profile')->where('user_id', $user->id)->first();
                    $profile->delete();

                }

            });

            //Increase the Max Allowed uploaded file for profile avatars
            $user->bindEvent('model.beforeValidate', function() use ($user) {
                $user->rules['avatar'] = 'nullable|image|max:2048';
            });
        });
    }

    public function registerMailTemplates()
    {
        return [
            'artistro08.profiles::mail.notifications',
        ];
    }
}
