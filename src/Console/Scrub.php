<?php namespace Carwash\Console;

use Faker;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class Scrub extends Command
{
    /**
     * @var string
     */
    protected $signature = 'carwash:scrub';

    /**
     * @var string
     */
    protected $description = 'Scrub data in the database';

    /**
     * @var Faker\Generator
     */
    protected $faker;

    /**
     * Scrub constructor.
     * @param Faker\Factory $faker
     */
    public function __construct(Faker\Factory $faker)
    {
        $locale = config('carwash.locale', config('app.locale'));

        $this->faker = $faker->create($locale);

        parent::__construct();
    }

    /**
     *
     */
    public function handle()
    {
        $this->info('Entering Carwash...');
        $this->line('');

        collect(config('carwash.tables'))->each(function ($fields, string $table) {
            $this->info(sprintf('Scrubbing table <error>%s</error>...', $table));

            $records = $this->getRecordsFromTable($table);
            $total = $records->count();

            $this->info(sprintf('Found %d records...', $total));

            $progressBar = $this->output->createProgressBar($total);
            $progressBar->start();

            $records->each(function ($record) use ($fields, $table, $progressBar) {
                $this->scrubRecord((array) $record, $table, $fields);

                $progressBar->advance();
            });

            $progressBar->finish();

            $this->info(sprintf('<error>%s</error> table scrubbed.', $table));
            $this->line('');
        });

        $this->info('Exiting Carwash...');
    }

    /**
     * @param $record
     * @param $table
     * @param $fields
     */
    private function scrubRecord($record, $table, $fields)
    {
        $this->makeModel($table, $record)->update($this->getUpdateData($fields, $record));
    }

    /**
     * @param $fields
     * @param $record
     * @return mixed
     */
    private function getUpdateData($fields, $record)
    {
        if (is_callable($fields)) {
            return $fields($this->faker, $record);
        }

        return collect($fields)->mapWithKeys(function ($fakerKey, $field) use ($record) {
            if (is_null($fakerKey)) {
                return [$field => null];
            }

            if (is_callable($fakerKey)) {
                return [$field => $fakerKey($this->faker, $record[$field])];
            }

            if (str_contains($fakerKey, ':')) {
                $formatter = explode(":", $fakerKey)[0];
                $arguments = explode(",", explode(":", $fakerKey)[1]);

                return [$field => $this->faker->{$formatter}(...$arguments)];
            }

            return [$field => $this->faker->{$fakerKey}];
        })->toArray();
    }

    /**
     * @param $table
     * @return mixed
     */
    private function getRecordsFromTable($table)
    {
        return \DB::table($table)->get();
    }

    /**
     * @param $table
     * @param $attributes
     * @return mixed
     */
    private function makeModel($table, $attributes)
    {
        return tap(new class extends Model
        {
            public $exists = true;
            protected $guarded = [];
        }, function ($model) use ($table, $attributes) {
            $model->setTable($table);
            $model->fill($attributes);
        });
    }

}
