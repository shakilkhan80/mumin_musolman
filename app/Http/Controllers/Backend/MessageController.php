<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ChannelSubscriber;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    public function index($channel_id = null) {
        $locked = null;
        if ($locked == 'locked') {
            $locked == true;
        }
        $user = Auth::user();
        $channels = Channel::all();
        foreach ($channels as $channel) {
            $lastMessage = Message::where('channel_id', $channel->id)->orderBy('created_at', 'desc')->first();
            if ($lastMessage) {
                $channel->last_message = $lastMessage;
            }
            $channel->other_subscriber = ChannelSubscriber::where('channel_id', $channel->id)->first();
        }

        $channels = collect($channels)->sortByDesc(function ($channel) {
            return optional($channel->last_message)->created_at ?? null;
        })->values()->all();
        $subscribers = ChannelSubscriber::where('channel_id', $channel_id)->where('user_id', '!=', $user->id)->get();
        $current_channel = Channel::find($channel_id);
        $current_channel->other_subscriber = ChannelSubscriber::where('channel_id', $current_channel->id)->first();
        $messages = Message::with('user')->where('channel_id',$channel_id)->get();
        foreach ($messages as $message) {
            $message->files = json_decode($message->files);
        }
        $data = [
            'locked' => $locked,
            'my_username' => $user->user_name,
            'my_user_id' => $user->id,
            'subscribers' => $subscribers,
            'channel_id' => $channel_id,
            'channels' => $channels,
        ];
        if ($current_channel) {
            $data['current_channel'] = $current_channel;
            $data['messages'] = $messages;
        }
        return view('backend.pages.message.index', $data);
    }

    public function messageSave(Request $request)
    {
        $message = new Message();

        $message->channel_id = $request->channel_id;
        $message->user_id = Auth::user()->id;
        $message->message = $request->message;

        if ($request->hasFile('files')) {
            $files = [];
            $images = $request->file('files');
            foreach ($images as $image) {
                $filename = time() . uniqid() . $image->getClientOriginalName();
                $image->move(public_path('uploads/message-files/'), $filename);

                $extension = strtolower($image->getClientOriginalExtension());

                if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp'])) {
                    $type = 'image';
                } elseif (in_array($extension, ['mp4', 'avi', 'mov', 'mkv', 'wmv'])) {
                    $type = 'video';
                } else {
                    $type = 'document';
                }

                $files[] = [
                    "type" => $type,
                    "path" => 'uploads/message-files/' . $filename
                ];
            }
            $message->files = json_encode($files);
        }

        $message->save();

        $response = [
            'success' => true,
            'fullMessage' => $message
        ];
        return response()->json($response);
    }

    public function reloadChannelContainer(Request $request)
    {
        $user = Auth::user();
        $all_channels = Channel::query();
        if($request->name){
            $all_channels->whereHas('subscribers.user', function ($query) use ($request) {
                $query->where('name', 'LIKE', '%' . $request->name . '%');
            });
        }
        $all_channels = $all_channels->get();

        $channels = [];
        foreach ($all_channels as $channel) {
            $lastMessage = Message::where('channel_id', $channel->id)->orderBy('created_at', 'desc')->first();
            if ($lastMessage) {
                $channel->last_message = $lastMessage;
            }
            $is_subscriber = ChannelSubscriber::where('channel_id', $channel->id)->where('user_id', $user->id)->first();
            $other_subscriber = ChannelSubscriber::where('channel_id', $channel->id)->where('user_id', '!=', $user->id)->first();
            $channel->other_subscriber = $other_subscriber;
            if ($is_subscriber) {
                $channels[] = $channel;
            }
        }

        $channels = collect($channels)->sortByDesc(function ($channel) {
            return optional($channel->last_message)->created_at ?? null;
        })->values()->all();
        $data = [
            'channels' => $channels,
            'my_user_id' => $user->id
        ];

        return view('backend.pages.message.message-users', $data);
    }
}
