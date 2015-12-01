<?php

namespace gazedb\tools;

use gazedb\Database;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class ClassGeneratorCommand extends  Command
{

  protected function configure()
  {
    $this
      ->setName('class:generate')
      ->setDescription('Generate PHP class from given table.')
      ->addOption(
        'desc',
        null,
        InputOption::VALUE_NONE,
        'Do not connect, use DESCRIBE result from stdin instead.'
      )
      ->addOption(
        'dsn',
        null,
        InputOption::VALUE_REQUIRED,
        'DSN connection string'
      )
      ->addOption(
        'username',
        null,
        InputOption::VALUE_REQUIRED,
        'DB username'
      )
      ->addOption(
        'password',
        null,
        InputOption::VALUE_OPTIONAL,
        'DB password. Prompted if omitted.'
      )
      ->addOption(
        'table',
        null,
        InputOption::VALUE_REQUIRED,
        'Table to analyze'
      )
      ->addOption(
        'class',
        null,
        InputOption::VALUE_REQUIRED,
        'Name of class to generate'
      )
      ->addOption(
        'namespace',
        null,
        InputOption::VALUE_REQUIRED,
        'Namespace of the model class'
      )
    ;
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {

    $desc = $input->getOption('desc');


    $dsn = $input->getOption('dsn');
    if ((! $dsn) && (! $desc)) {
      $output->writeln('Missing <error>dsn</error> option.');
      return;
    }

    if ($dsn && $desc) {
      $output->writeln('Cannot use both <error>dsn</error> and <error>desc</error> options.');
      return;
    }

    $username = $input->getOption('username');
    if ((! $username) && $dsn) {
      $output->writeln('Missing <error>username</error> option.');
      return;
    }

    $table = $input->getOption('table');
    if (! $table) {
      $output->writeln('Missing <error>table</error> option.');
      return;
    }

    $className = $input->getOption('class');
    if (! $className) {
      $output->writeln('Missing <error>class</error> option.');
      return;
    }

    $namespace = $input->getOption('namespace');


    $password = $input->getOption('password');
    if (! $password) {
      $helper = $this->getHelper('question');
      $question = new Question('Enter password: ', false);
      $question->setHidden(true);
      $question->setHiddenFallback(false);

      $password = $helper->ask($input, $output, $question);
    }




    if ($dsn) {
      // If a DSN is provided, let's connect to the database and issue a DESC statement
      $output->write('Connecting to DB...');
      $db = Database::get();
      $db->injectDsn($dsn, $username, $password);
      $db->pdo()->query('select now()');
      $output->writeln('[<info>OK</info>]');

      $output->write('Analyzing table...');
      $rs = $db->pdo()->query('describe ' . $table);
      $tableDef = $rs->fetchAll();
      $output->writeln('[<info>OK</info>]');
    }

    else {
      // Table DESC result comes from stdin
      $stdin = file('php://stdin');
      $fieldsPos = null;
      $tableDef = [];

      foreach ($stdin as $line) {
        $line = trim($line);

        // The first line indicates the column names
        // Let's split them and capture the starting position of each within string
        if (null === $fieldsPos) {
          $fieldsPos = preg_split('#[[:blank:]]+#', $line, 0, PREG_SPLIT_OFFSET_CAPTURE);
          // array of 0=>colname, 1=>startPos
          continue;
        }

        // Separator line
        // Right after the column headers, comes a line with many ------
        if (substr($line, 0, 1) == '-') {
          continue;
        }

        // Regular line: a table field and its definitions.
        // We know the starting position of the value of each property,
        // so we can extract the properties and populate the $tableDef structure
        // as if it came directly from PDO.
        $values = [];
        foreach ($fieldsPos as $fieldPos) {
          $columnTitle = $fieldPos[0];
          $startPos = $fieldPos[1];

          $value = preg_replace('#[[:blank:]].*$#', '', substr($line, $startPos));
          $values[$columnTitle] = $value;
        }
        $tableDef []= $values;
      }
    }






    // Prepare to learn some useful info about the table
    $modelInfo = [
      'columns' => [],
      'pk' => [],
      'auto' => null
    ];


    foreach ($tableDef as $column) {
      $fieldName = $column['Field'];

      // No white space in const names
      $constName = str_replace(' ', '_', strtoupper($fieldName));
      // Const name cannot begin with digit
      if (preg_match('#^[0-9]#', $constName)) {
        $constName = '_' . $constName;
      }

      $modelInfo['columns'] []= [
        'field' => $fieldName,
        'const' => $constName
      ];

      // The primary key can be composed of several fields.
      // In Mysql, they would have the PRI indication in Key describe result
      if ($column['Key'] == 'PRI') {
        $modelInfo['pk'] []= $constName;
      }

      // Auto-increment: column Extra
      if ($column['Extra'] == 'auto_increment') {
        $modelInfo['auto'] = $constName;
      }
    }



    //
    // Produce the generate code
    //

    //
    // Class header
    //
    $output->writeln('<?php');
    $output->writeln('');
    if ($namespace) {
      $output->writeln('namespace ' . $namespace . ';');
      $output->writeln('');
    }
    $output->writeln('use gazedb\ModelObject;');
    $output->writeln('');
    $output->writeln('class ' . $className . ' extends ModelObject');
    $output->writeln('{');


    //
    // Field constants
    //
    foreach ($modelInfo['columns'] as $column) {
      $output->writeln('  const ' . $column['const']. ' = \''. $column['field'] .'\';');
    }

    $output->writeln('');

    //
    // tableName
    //
    $output->writeln('  protected static function tableName() { return \''.$table.'\'; }');
    $output->writeln('');


    //
    // mapFields
    //
    $output->writeln('  public static function mapFields()');
    $output->writeln('  {');
    $output->writeln('    return [');
    foreach ($modelInfo['columns'] as $column) {
      $output->writeln('      self::' . $column['const'] . ' => null,');
    }
    $output->writeln('    ];');
    $output->writeln('  }');
    $output->writeln('');


    //
    // mapPK
    //
    if (count($modelInfo['pk'])) {
      $pks = array_map(function ($item) { return 'self::' . $item; }, $modelInfo['pk']);
      $output->writeln('  public function mapPK()');
      $output->writeln('  {');
      $output->writeln('    return [ '.implode(', ', $pks).' ];');
      $output->writeln('  }');
      $output->writeln('');
    }


    //
    // mapAutoIncrement
    //
    if($modelInfo['auto']) {
      $output->writeln('  public function mapAutoIncrement()');
      $output->writeln('  {');
      $output->writeln('    return self::'.$modelInfo['auto'].';');
      $output->writeln('  }');
      $output->writeln('');
    }

    //
    // Mutators
    //
    foreach ($modelInfo['columns'] as $column) {
      $handler = function (array $matches) {
        return strtoupper($matches[1]);
      };

      $camelCase = preg_replace('#^_#', '', $column['const']);
      $camelCase = substr($camelCase, 0, 1) . strtolower(substr($camelCase, 1));
      $camelCase = preg_replace_callback('#_(.)#', $handler, $camelCase);
      $output->writeln('  public function get'.$camelCase.'() { return $this->column(self::'.$column['const'].'); }');
      $output->writeln('  /**');
      $output->writeln('   * @return ' . $className);
      $output->writeln('   */');
      $output->writeln('  public function set'.$camelCase.'($value) { return $this->assign(self::'.$column['const'].', $value); }');
    }

    //
    // End of class
    //
    $output->writeln('}');
    $output->writeln('');

  }
}
