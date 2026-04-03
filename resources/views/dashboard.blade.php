<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }} - Dashboard</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
</head>
<body class="font-sans antialiased bg-gray-100">
    <div class="min-h-screen">
        <!-- Navigation -->
        <nav class="bg-white shadow-lg">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <h1 class="text-xl font-bold text-gray-900">Hotel Management System</h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-700">
                            Welcome, <strong>{{ $user->name }}</strong>
                        </span>
                        <span class="text-sm text-gray-500">
                            ({{ $user->role->name ?? 'No Role' }})
                        </span>
                        @if($selectedModule)
                            <span class="px-3 py-1 bg-indigo-100 text-indigo-800 rounded-full text-sm">
                                Module: {{ ucfirst(str_replace('-', ' ', $selectedModule)) }}
                            </span>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-sm text-red-600 hover:text-red-800">
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="px-4 py-6 sm:px-0">
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Dashboard</h2>
                    
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-700 mb-3">Your Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600">Name:</p>
                                <p class="text-base font-medium">{{ $user->name }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Email:</p>
                                <p class="text-base font-medium">{{ $user->email }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Role:</p>
                                <p class="text-base font-medium">{{ $user->role->name ?? 'No Role' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Department:</p>
                                <p class="text-base font-medium">{{ $user->department->name ?? 'No Department' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-700 mb-3">Accessible Modules</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @forelse($modules as $module)
                                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                    <h4 class="font-semibold text-gray-900">{{ $module->name }}</h4>
                                    @if($module->description)
                                        <p class="text-sm text-gray-600 mt-2">{{ $module->description }}</p>
                                    @endif
                                </div>
                            @empty
                                <p class="text-gray-500">No modules available.</p>
                            @endforelse
                        </div>
                    </div>

                    @if($user->isSuperAdmin())
                        <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <p class="text-sm text-yellow-800">
                                <strong>Super Admin Access:</strong> You have full system access and can manage all configurations, packages, and users.
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </main>
    </div>
    @livewireScripts
</body>
</html>
