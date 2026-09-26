@extends('layouts.admin')

@section('content')
    <div class="mb-6">
        <h2 class="text-xl font-bold">Work Configuration</h2>
        <p class="mt-1 text-sm text-slate-500">Used configuration is deactivated instead of hard-deleted so historical tasks remain readable.</p>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="font-bold">Tiers</h3>
            <form method="POST" action="{{ route('tiers.store') }}" class="mt-4 flex gap-2">
                @csrf
                <input name="name" required placeholder="VIP Tier" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2">
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Add</button>
            </form>

            <div class="mt-4 space-y-2">
                @foreach($tiers as $tier)
                    <form method="POST" action="{{ route('admin.settings.catalogs.update', ['catalog' => 'tiers', 'id' => $tier->id]) }}" class="grid gap-2 rounded-xl border border-slate-200 p-3 sm:grid-cols-[1fr_auto_auto]">
                        @csrf
                        @method('PUT')
                        <input name="name" value="{{ $tier->name }}" class="rounded-lg border border-slate-300 px-3 py-2">
                        <input type="hidden" name="is_active" value="0">
                        <label class="flex items-center gap-2 px-2 text-sm">
                            <input type="checkbox" name="is_active" value="1" @checked($tier->is_active)> Active
                        </label>
                        <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">Save</button>
                    </form>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="font-bold">Asset Sections</h3>
            <form method="POST" action="{{ route('admin.settings.catalogs.store', 'asset-sections') }}" class="mt-4 grid gap-2 sm:grid-cols-[1fr_100px_auto]">
                @csrf
                <input name="name" required placeholder="Section name" class="rounded-lg border border-slate-300 px-3 py-2">
                <input name="sort_order" type="number" min="0" value="0" class="rounded-lg border border-slate-300 px-3 py-2">
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Add</button>
            </form>

            <div class="mt-4 space-y-2">
                @foreach($assetSections as $item)
                    <form method="POST" action="{{ route('admin.settings.catalogs.update', ['catalog' => 'asset-sections', 'id' => $item->id]) }}" class="grid gap-2 rounded-xl border border-slate-200 p-3 sm:grid-cols-[1fr_90px_auto_auto]">
                        @csrf
                        @method('PUT')
                        <input name="name" value="{{ $item->name }}" class="rounded-lg border border-slate-300 px-3 py-2">
                        <input name="sort_order" type="number" min="0" value="{{ $item->sort_order }}" class="rounded-lg border border-slate-300 px-3 py-2">
                        <input type="hidden" name="is_active" value="0">
                        <label class="flex items-center gap-2 px-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($item->is_active)> Active</label>
                        <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">Save</button>
                    </form>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
            <h3 class="font-bold">Social Activity Types / Full Activity preset</h3>
            <form method="POST" action="{{ route('admin.settings.catalogs.store', 'social-activities') }}" class="mt-4 grid gap-2 md:grid-cols-[1fr_100px_auto_auto]">
                @csrf
                <input name="name" required placeholder="Activity name" class="rounded-lg border border-slate-300 px-3 py-2">
                <input name="sort_order" type="number" min="0" value="0" class="rounded-lg border border-slate-300 px-3 py-2">
                <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 text-sm">
                    <input type="checkbox" name="is_full_activity_default" value="1"> Full Activity
                </label>
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Add</button>
            </form>

            <div class="mt-4 grid gap-2 lg:grid-cols-2">
                @foreach($socialActivities as $item)
                    <form method="POST" action="{{ route('admin.settings.catalogs.update', ['catalog' => 'social-activities', 'id' => $item->id]) }}" class="grid gap-2 rounded-xl border border-slate-200 p-3 sm:grid-cols-[1fr_80px_auto_auto_auto]">
                        @csrf
                        @method('PUT')
                        <input name="name" value="{{ $item->name }}" class="rounded-lg border border-slate-300 px-3 py-2">
                        <input name="sort_order" type="number" min="0" value="{{ $item->sort_order }}" class="rounded-lg border border-slate-300 px-3 py-2">

                        <input type="hidden" name="is_full_activity_default" value="0">
                        <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="is_full_activity_default" value="1" @checked($item->is_full_activity_default)> Full</label>

                        <input type="hidden" name="is_active" value="0">
                        <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="is_active" value="1" @checked($item->is_active)> Active</label>

                        <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">Save</button>
                    </form>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="font-bold">Custom Job Types</h3>
            <form method="POST" action="{{ route('admin.settings.catalogs.store', 'custom-job-types') }}" class="mt-4 grid gap-2">
                @csrf
                <input name="name" required placeholder="Job type" class="rounded-lg border border-slate-300 px-3 py-2">
                <textarea name="default_instruction" rows="2" placeholder="Default instruction template" class="rounded-lg border border-slate-300 px-3 py-2"></textarea>
                <button class="justify-self-start rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Add</button>
            </form>

            <div class="mt-4 space-y-2">
                @foreach($customJobTypes as $item)
                    <form method="POST" action="{{ route('admin.settings.catalogs.update', ['catalog' => 'custom-job-types', 'id' => $item->id]) }}" class="rounded-xl border border-slate-200 p-3">
                        @csrf
                        @method('PUT')
                        <div class="grid gap-2">
                            <input name="name" value="{{ $item->name }}" class="rounded-lg border border-slate-300 px-3 py-2">
                            <textarea name="default_instruction" rows="2" class="rounded-lg border border-slate-300 px-3 py-2">{{ $item->default_instruction }}</textarea>
                            <div class="flex items-center justify-between gap-3">
                                <input type="hidden" name="is_active" value="0">
                                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($item->is_active)> Active</label>
                                <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">Save</button>
                            </div>
                        </div>
                    </form>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="font-bold">QC Reasons</h3>
            <form method="POST" action="{{ route('admin.settings.catalogs.store', 'qc-reasons') }}" class="mt-4 grid gap-2 sm:grid-cols-[150px_1fr_auto]">
                @csrf
                <select name="type" class="rounded-lg border border-slate-300 px-3 py-2">
                    <option value="NEGATIVE">NEGATIVE</option>
                    <option value="BONUS">BONUS</option>
                </select>
                <input name="name" required placeholder="Reason" class="rounded-lg border border-slate-300 px-3 py-2">
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Add</button>
            </form>

            <div class="mt-4 space-y-2">
                @foreach($qcReasons as $item)
                    <form method="POST" action="{{ route('admin.settings.catalogs.update', ['catalog' => 'qc-reasons', 'id' => $item->id]) }}" class="grid gap-2 rounded-xl border border-slate-200 p-3 sm:grid-cols-[140px_1fr_auto_auto]">
                        @csrf
                        @method('PUT')
                        <select name="type" class="rounded-lg border border-slate-300 px-3 py-2">
                            <option value="NEGATIVE" @selected($item->type->value === 'NEGATIVE')>NEGATIVE</option>
                            <option value="BONUS" @selected($item->type->value === 'BONUS')>BONUS</option>
                        </select>
                        <input name="name" value="{{ $item->name }}" class="rounded-lg border border-slate-300 px-3 py-2">
                        <input type="hidden" name="is_active" value="0">
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($item->is_active)> Active</label>
                        <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">Save</button>
                    </form>
                @endforeach
            </div>
        </section>
    </div>
@endsection
