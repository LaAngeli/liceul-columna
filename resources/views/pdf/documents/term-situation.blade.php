@extends('pdf.documents._layout')

@section('title', 'Situația școlară')
@section('subtitle', $termLabel)

@section('body')
    @include('pdf.documents._term-tables')
@endsection
