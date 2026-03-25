@extends('statamic::layout')
@section('title', $title)

@section('content')
    <poll-dashboard 
        metrics-url="{{ cp_route('activitypub.polls.metrics') }}"
        voters-url="{{ cp_route('activitypub.polls.voters', ['poll' => 'ID_PLACEHOLDER']) }}"
        close-url="{{ cp_route('activitypub.polls.close', ['poll' => 'ID_PLACEHOLDER']) }}"
    ></poll-dashboard>
@endsection
