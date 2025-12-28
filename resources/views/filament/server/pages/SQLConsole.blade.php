<x-filament-panels::page>
    
    <x-filament::section>
        <x-slot name="heading">
            Execute SQL
        </x-slot>
        <x-slot name="description">
            Run raw SQL commands directly against your database.
        </x-slot>

        {{ $this->form }}

        <div class="mt-4 flex justify-end">
            <x-filament::button wire:click="runQuery" icon="heroicon-m-play" size="md">
                Run Query
            </x-filament::button>
        </div>
    </x-filament::section>

    @if(!is_null($tables))
        <x-filament::section>
            <x-slot name="heading">Database Tables</x-slot>
            
            @if(count($tables) > 0)
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-2">
                    @foreach($tables as $table)
                        <button 
                            wire:click="selectTable('{{ $table }}')"
                            class="px-3 py-2 text-xs font-mono bg-gray-100 dark:bg-gray-800 hover:bg-primary-500 hover:text-white border border-gray-200 dark:border-gray-700 rounded transition text-left truncate"
                            title="Query {{ $table }}">
                            {{ $table }}
                        </button>
                    @endforeach
                </div>
            @else
                <div class="text-gray-500 text-sm italic">No tables found in this database.</div>
            @endif
        </x-filament::section>
    @endif

    @if($queryError)
        <div class="p-4 rounded-xl bg-danger-50 dark:bg-danger-500/10 border border-danger-200 dark:border-danger-500/20 text-danger-700 dark:text-danger-400">
            <div class="flex items-center gap-2 font-bold mb-1">
                <x-heroicon-m-x-circle class="w-5 h-5" />
                Error
            </div>
            <code class="text-sm font-mono break-all">{{ $queryError }}</code>
        </div>
    @endif

    @if(!is_null($queryResults))
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center justify-between w-full">
                    <div class="flex items-center gap-2">
                        Results 
                        <span class="text-gray-500 font-normal text-sm">({{ count($queryResults) }} rows in {{ $queryTime }}ms)</span>
                    </div>
                    @if(count($queryResults) > 0)
                        <x-filament::button wire:click="downloadCsv" icon="heroicon-m-arrow-down-tray" color="gray" size="sm" outlined>
                            Download CSV
                        </x-filament::button>
                    @endif
                </div>
            </x-slot>

            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                @if(count($queryResults) > 0)
                    <table class="w-full text-left text-sm border-separate border-spacing-0">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-900">
                                @foreach(array_keys($queryResults[0]) as $header)
                                    <th class="px-6 py-4 font-bold text-gray-700 dark:text-gray-200 border-b border-gray-200 dark:border-white/10 whitespace-nowrap uppercase tracking-wider text-xs">
                                        {{ $header }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                            @foreach($queryResults as $row)
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                    @foreach($row as $cell)
                                        <td class="px-6 py-3 font-mono text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                            {{ is_array($cell) ? json_encode($cell) : $cell }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="p-8 text-center text-gray-500">
                        No results returned.
                    </div>
                @endif
            </div>
        </x-filament::section>
    @endif

</x-filament-panels::page>