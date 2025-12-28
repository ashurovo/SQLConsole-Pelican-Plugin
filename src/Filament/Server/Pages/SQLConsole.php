<?php

namespace Ashurov\SQLConsole\Filament\Server\Pages;

use App\Models\Database;
use App\Models\Server;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Filament\Facades\Filament;

class SQLConsole extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-command-line';
    
    protected static ?string $navigationLabel = 'SQL Console';
    protected static ?string $title = 'SQL Console';
    protected static ?string $slug = 'sql-console';
    protected static ?int $navigationSort = 7;

    protected string $view = 'sqlconsole::filament.server.pages.SQLConsole';

    public ?array $data = [];
    public $queryResults = null;
    public $queryError = null;
    public $queryTime = 0;
    
    public $tables = null; 

    public Server $server;

    public static function shouldRegisterNavigation(): bool
    {
        if (Filament::getCurrentPanel()->getId() !== 'server') {
            return false;
        }
        return Filament::getTenant()->databases()->exists();
    }

    public function mount(): void
    {
        $this->server = Filament::getTenant();
        $firstDb = $this->server->databases()->first();

        if (!$firstDb) {
            return;
        }

        $this->form->fill([
            'database_id' => $firstDb->id,
            'custom_query' => "SELECT * FROM users LIMIT 10;",
        ]);
        $this->fetchTables();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('database_id')
                    ->label('Target Database')
                    ->options(fn() => $this->server->databases()->pluck('database', 'id'))
                    ->required()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function () {
                        $this->fetchTables();
                    }),
                
                Textarea::make('custom_query')
                    ->label('SQL Query')
                    ->rows(10)
                    ->required()
                    ->hint('Standard MySQL syntax supported')
                    ->extraAttributes(['class' => 'font-mono text-sm']),
            ])
            ->statePath('data');
    }

    public function fetchTables(): void
    {
        $dbId = $this->data['database_id'] ?? null;
        if (!$dbId) return;

        try {
            $pdo = $this->getDatabaseConnection($dbId);
            $stmt = $pdo->prepare("SHOW TABLES");
            $stmt->execute();
            
            $this->tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
        } catch (\Exception $e) {
            Notification::make()->title('Could not list tables')->body($e->getMessage())->danger()->send();
            $this->tables = [];
        }
    }

    public function selectTable(string $tableName): void
    {
        $this->data['custom_query'] = "SELECT * FROM `{$tableName}` LIMIT 100;";
        $this->runQuery();
    }

    public function runQuery(): void
    {
        $this->queryResults = null;
        $this->queryError = null;
        $this->queryTime = 0;

        $dbId = $this->data['database_id'] ?? null;
        $sql = $this->data['custom_query'] ?? null;

        if (!$dbId || !$sql) return;

        try {
            $pdo = $this->getDatabaseConnection($dbId);
            
            $start = microtime(true);
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $this->queryTime = round((microtime(true) - $start) * 1000, 2);

            if (stripos(trim($sql), 'SELECT') === 0 || stripos(trim($sql), 'SHOW') === 0 || stripos(trim($sql), 'DESCRIBE') === 0) {
                $this->queryResults = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                Notification::make()->title('Query successful')->body("Fetched " . count($this->queryResults) . " rows in {$this->queryTime}ms")->success()->send();
            } else {
                $this->queryResults = [['Message' => 'Query executed successfully', 'Rows Affected' => $stmt->rowCount()]];
                Notification::make()->title('Command executed')->success()->send();
            }

        } catch (\Exception $e) {
            $this->queryError = $e->getMessage();
            Notification::make()->title('Query Failed')->danger()->send();
        }
    }

    public function downloadCsv()
    {
        if (empty($this->queryResults)) {
            Notification::make()->title('No data to export')->warning()->send();
            return;
        }

        $headers = array_keys($this->queryResults[0]);
        $rows = $this->queryResults;

        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                $row = array_map(fn($item) => is_array($item) ? json_encode($item) : $item, $row);
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 'query_results_' . date('Y-m-d_His') . '.csv');
    }

    private function getDatabaseConnection($dbId)
    {
        $database = Database::where('id', $dbId)->where('server_id', $this->server->id)->firstOrFail();
     
        $host = $database->host;
        $username = $database->username;
     
        try {
            $password = Crypt::decryptString($database->password);
        } catch (\Exception $e) {
            $password = $database->password;
        }

        Config::set("database.connections.dynamic_user_sql", [
            'driver' => 'mysql',
            'host' => $host->host,
            'port' => $host->port,
            'database' => $database->database,
            'username' => $username,
            'password' => $password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ]);

        DB::purge('dynamic_user_sql');
        return DB::connection('dynamic_user_sql')->getPdo();
    }
}