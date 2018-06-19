<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;

class DB2PlatformTest extends AbstractPlatformTestCase
{
    /**
     * @var \Doctrine\DBAL\Platforms\DB2Platform
     */
    protected $_platform;

    public function createPlatform()
    {
        return new DB2Platform();
    }

    public function getGenerateAlterTableSql()
    {
        return array(
            "ALTER TABLE mytable ALTER COLUMN baz SET DATA TYPE VARCHAR(255)",
            "ALTER TABLE mytable ALTER COLUMN baz SET NOT NULL",
            "ALTER TABLE mytable ALTER COLUMN baz SET DEFAULT 'def'",
            "ALTER TABLE mytable ALTER COLUMN bloo SET DATA TYPE SMALLINT",
            "ALTER TABLE mytable ALTER COLUMN bloo SET NOT NULL",
            "ALTER TABLE mytable ALTER COLUMN bloo SET DEFAULT '0'",
            "ALTER TABLE mytable " .
            "ADD COLUMN quota INTEGER DEFAULT NULL " .
            "DROP COLUMN foo",
            "CALL SYSPROC.ADMIN_CMD ('REORG TABLE mytable')",
            'RENAME TABLE mytable TO userlist',
        );
    }

    public function getGenerateForeignKeySql()
    {
        return 'ALTER TABLE test ADD FOREIGN KEY (fk_name_id) REFERENCES other_table (id)';
    }

    public function getGenerateIndexSql()
    {
        return 'CREATE INDEX my_idx ON mytable (user_name, last_login)';
    }

    public function getGenerateTableSql()
    {
        return 'CREATE TABLE test (id INTEGER GENERATED BY DEFAULT AS IDENTITY NOT NULL, test VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))';
    }

    public function getGenerateTableWithMultiColumnUniqueIndexSql()
    {
        return array(
            'CREATE TABLE test (foo VARCHAR(255) DEFAULT NULL, bar VARCHAR(255) DEFAULT NULL)',
            'CREATE UNIQUE INDEX UNIQ_D87F7E0C8C73652176FF8CAA ON test (foo, bar)'
        );
    }

    public function getGenerateUniqueIndexSql()
    {
        return 'CREATE UNIQUE INDEX index_name ON test (test, test2)';
    }

    protected function getQuotedColumnInForeignKeySQL()
    {
        return array(
            'CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL, foo VARCHAR(255) NOT NULL, "bar" VARCHAR(255) NOT NULL)',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar") REFERENCES "foreign" ("create", bar, "foo-bar")',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_NON_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar") REFERENCES foo ("create", bar, "foo-bar")',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_INTENDED_QUOTATION FOREIGN KEY ("create", foo, "bar") REFERENCES "foo-bar" ("create", bar, "foo-bar")',
        );
    }

    protected function getQuotedColumnInIndexSQL()
    {
        return array(
            'CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL)',
            'CREATE INDEX IDX_22660D028FD6E0FB ON "quoted" ("create")'
        );
    }

    protected function getQuotedNameInIndexSQL()
    {
        return array(
            'CREATE TABLE test (column1 VARCHAR(255) NOT NULL)',
            'CREATE INDEX "key" ON test (column1)',
        );
    }

    protected function getQuotedColumnInPrimaryKeySQL()
    {
        return array(
            'CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL, PRIMARY KEY("create"))'
        );
    }

    protected function getBitAndComparisonExpressionSql($value1, $value2)
    {
        return 'BITAND(' . $value1 . ', ' . $value2 . ')';
    }

    protected  function getBitOrComparisonExpressionSql($value1, $value2)
    {
        return 'BITOR(' . $value1 . ', ' . $value2 . ')';
    }

    public function getCreateTableColumnCommentsSQL()
    {
        return array(
            "CREATE TABLE test (id INTEGER NOT NULL, PRIMARY KEY(id))",
            "COMMENT ON COLUMN test.id IS 'This is a comment'",
        );
    }

    public function getAlterTableColumnCommentsSQL()
    {
        return array(
            "ALTER TABLE mytable " .
            "ADD COLUMN quota INTEGER NOT NULL WITH DEFAULT",
            "CALL SYSPROC.ADMIN_CMD ('REORG TABLE mytable')",
            "COMMENT ON COLUMN mytable.quota IS 'A comment'",
            "COMMENT ON COLUMN mytable.foo IS ''",
            "COMMENT ON COLUMN mytable.baz IS 'B comment'",
        );
    }

    public function getCreateTableColumnTypeCommentsSQL()
    {
        return array(
            'CREATE TABLE test (id INTEGER NOT NULL, "data" CLOB(1M) NOT NULL, PRIMARY KEY(id))',
            'COMMENT ON COLUMN test."data" IS \'(DC2Type:array)\'',
        );
    }

    public function testHasCorrectPlatformName()
    {
        self::assertEquals('db2', $this->_platform->getName());
    }

    public function testGeneratesCreateTableSQLWithCommonIndexes()
    {
        $table = new Table('test');
        $table->addColumn('id', 'integer');
        $table->addColumn('name', 'string', array('length' => 50));
        $table->setPrimaryKey(array('id'));
        $table->addIndex(array('name'));
        $table->addIndex(array('id', 'name'), 'composite_idx');

        self::assertEquals(
            array(
                'CREATE TABLE test (id INTEGER NOT NULL, name VARCHAR(50) NOT NULL, PRIMARY KEY(id))',
                'CREATE INDEX IDX_D87F7E0C5E237E06 ON test (name)',
                'CREATE INDEX composite_idx ON test (id, name)'
            ),
            $this->_platform->getCreateTableSQL($table)
        );
    }

    public function testGeneratesCreateTableSQLWithForeignKeyConstraints()
    {
        $table = new Table('test');
        $table->addColumn('id', 'integer');
        $table->addColumn('fk_1', 'integer');
        $table->addColumn('fk_2', 'integer');
        $table->setPrimaryKey(array('id'));
        $table->addForeignKeyConstraint('foreign_table', array('fk_1', 'fk_2'), array('pk_1', 'pk_2'));
        $table->addForeignKeyConstraint(
            'foreign_table2',
            array('fk_1', 'fk_2'),
            array('pk_1', 'pk_2'),
            array(),
            'named_fk'
        );

        self::assertEquals(
            array(
                'CREATE TABLE test (id INTEGER NOT NULL, fk_1 INTEGER NOT NULL, fk_2 INTEGER NOT NULL)',
                'ALTER TABLE test ADD CONSTRAINT FK_D87F7E0C177612A38E7F4319 FOREIGN KEY (fk_1, fk_2) REFERENCES foreign_table (pk_1, pk_2)',
                'ALTER TABLE test ADD CONSTRAINT named_fk FOREIGN KEY (fk_1, fk_2) REFERENCES foreign_table2 (pk_1, pk_2)',
            ),
            $this->_platform->getCreateTableSQL($table, AbstractPlatform::CREATE_FOREIGNKEYS)
        );
    }

    public function testGeneratesCreateTableSQLWithCheckConstraints()
    {
        $table = new Table('test');
        $table->addColumn('id', 'integer');
        $table->addColumn('check_max', 'integer', array('platformOptions' => array('max' => 10)));
        $table->addColumn('check_min', 'integer', array('platformOptions' => array('min' => 10)));
        $table->setPrimaryKey(array('id'));

        self::assertEquals(
            array(
                'CREATE TABLE test (id INTEGER NOT NULL, check_max INTEGER NOT NULL, check_min INTEGER NOT NULL, PRIMARY KEY(id), CHECK (check_max <= 10), CHECK (check_min >= 10))'
            ),
            $this->_platform->getCreateTableSQL($table)
        );
    }

    public function testGeneratesColumnTypesDeclarationSQL()
    {
        $fullColumnDef = array(
            'length' => 10,
            'fixed' => true,
            'unsigned' => true,
            'autoincrement' => true
        );

        self::assertEquals('VARCHAR(255)', $this->_platform->getVarcharTypeDeclarationSQL(array()));
        self::assertEquals('VARCHAR(10)', $this->_platform->getVarcharTypeDeclarationSQL(array('length' => 10)));
        self::assertEquals('CHAR(254)', $this->_platform->getVarcharTypeDeclarationSQL(['fixed' => true]));
        self::assertEquals('CHAR(10)', $this->_platform->getVarcharTypeDeclarationSQL($fullColumnDef));

        self::assertEquals('SMALLINT', $this->_platform->getSmallIntTypeDeclarationSQL(array()));
        self::assertEquals('SMALLINT', $this->_platform->getSmallIntTypeDeclarationSQL(array(
            'unsigned' => true
        )));
        self::assertEquals('SMALLINT GENERATED BY DEFAULT AS IDENTITY', $this->_platform->getSmallIntTypeDeclarationSQL($fullColumnDef));
        self::assertEquals('INTEGER', $this->_platform->getIntegerTypeDeclarationSQL(array()));
        self::assertEquals('INTEGER', $this->_platform->getIntegerTypeDeclarationSQL(array(
            'unsigned' => true
        )));
        self::assertEquals('INTEGER GENERATED BY DEFAULT AS IDENTITY', $this->_platform->getIntegerTypeDeclarationSQL($fullColumnDef));
        self::assertEquals('BIGINT', $this->_platform->getBigIntTypeDeclarationSQL(array()));
        self::assertEquals('BIGINT', $this->_platform->getBigIntTypeDeclarationSQL(array(
            'unsigned' => true
        )));
        self::assertEquals('BIGINT GENERATED BY DEFAULT AS IDENTITY', $this->_platform->getBigIntTypeDeclarationSQL($fullColumnDef));
        self::assertEquals('BLOB(1M)', $this->_platform->getBlobTypeDeclarationSQL($fullColumnDef));
        self::assertEquals('SMALLINT', $this->_platform->getBooleanTypeDeclarationSQL($fullColumnDef));
        self::assertEquals('CLOB(1M)', $this->_platform->getClobTypeDeclarationSQL($fullColumnDef));
        self::assertEquals('DATE', $this->_platform->getDateTypeDeclarationSQL($fullColumnDef));
        self::assertEquals('TIMESTAMP(0) WITH DEFAULT', $this->_platform->getDateTimeTypeDeclarationSQL(array('version' => true)));
        self::assertEquals('TIMESTAMP(0)', $this->_platform->getDateTimeTypeDeclarationSQL($fullColumnDef));
        self::assertEquals('TIME', $this->_platform->getTimeTypeDeclarationSQL($fullColumnDef));
    }

    public function testInitializesDoctrineTypeMappings()
    {
        $this->_platform->initializeDoctrineTypeMappings();

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('smallint'));
        self::assertSame('smallint', $this->_platform->getDoctrineTypeMapping('smallint'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('bigint'));
        self::assertSame('bigint', $this->_platform->getDoctrineTypeMapping('bigint'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('integer'));
        self::assertSame('integer', $this->_platform->getDoctrineTypeMapping('integer'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('time'));
        self::assertSame('time', $this->_platform->getDoctrineTypeMapping('time'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('date'));
        self::assertSame('date', $this->_platform->getDoctrineTypeMapping('date'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('varchar'));
        self::assertSame('string', $this->_platform->getDoctrineTypeMapping('varchar'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('character'));
        self::assertSame('string', $this->_platform->getDoctrineTypeMapping('character'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('clob'));
        self::assertSame('text', $this->_platform->getDoctrineTypeMapping('clob'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('blob'));
        self::assertSame('blob', $this->_platform->getDoctrineTypeMapping('blob'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('decimal'));
        self::assertSame('decimal', $this->_platform->getDoctrineTypeMapping('decimal'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('double'));
        self::assertSame('float', $this->_platform->getDoctrineTypeMapping('double'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('real'));
        self::assertSame('float', $this->_platform->getDoctrineTypeMapping('real'));

        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('timestamp'));
        self::assertSame('datetime', $this->_platform->getDoctrineTypeMapping('timestamp'));
    }

    public function getIsCommentedDoctrineType()
    {
        $data = parent::getIsCommentedDoctrineType();

        $data[Type::BOOLEAN] = array(Type::getType(Type::BOOLEAN), true);

        return $data;
    }

    public function testGeneratesDDLSnippets()
    {
        self::assertEquals("CREATE DATABASE foobar", $this->_platform->getCreateDatabaseSQL('foobar'));
        self::assertEquals("DROP DATABASE foobar", $this->_platform->getDropDatabaseSQL('foobar'));
        self::assertEquals('DECLARE GLOBAL TEMPORARY TABLE', $this->_platform->getCreateTemporaryTableSnippetSQL());
        self::assertEquals('TRUNCATE foobar IMMEDIATE', $this->_platform->getTruncateTableSQL('foobar'));
        self::assertEquals('TRUNCATE foobar IMMEDIATE', $this->_platform->getTruncateTableSQL('foobar'), true);

        $viewSql = 'SELECT * FROM footable';
        self::assertEquals('CREATE VIEW fooview AS ' . $viewSql, $this->_platform->getCreateViewSQL('fooview', $viewSql));
        self::assertEquals('DROP VIEW fooview', $this->_platform->getDropViewSQL('fooview'));
    }

    public function testGeneratesCreateUnnamedPrimaryKeySQL()
    {
        self::assertEquals(
            'ALTER TABLE foo ADD PRIMARY KEY (a, b)',
            $this->_platform->getCreatePrimaryKeySQL(
                new Index('any_pk_name', array('a', 'b'), true, true),
                'foo'
            )
        );
    }

    public function testGeneratesSQLSnippets()
    {
        self::assertEquals('CURRENT DATE', $this->_platform->getCurrentDateSQL());
        self::assertEquals('CURRENT TIME', $this->_platform->getCurrentTimeSQL());
        self::assertEquals('CURRENT TIMESTAMP', $this->_platform->getCurrentTimestampSQL());
        self::assertEquals("'1987/05/02' + 4 DAY", $this->_platform->getDateAddDaysExpression("'1987/05/02'", 4));
        self::assertEquals("'1987/05/02' + 12 HOUR", $this->_platform->getDateAddHourExpression("'1987/05/02'", 12));
        self::assertEquals("'1987/05/02' + 2 MINUTE", $this->_platform->getDateAddMinutesExpression("'1987/05/02'", 2));
        self::assertEquals("'1987/05/02' + 102 MONTH", $this->_platform->getDateAddMonthExpression("'1987/05/02'", 102));
        self::assertEquals("'1987/05/02' + 15 MONTH", $this->_platform->getDateAddQuartersExpression("'1987/05/02'", 5));
        self::assertEquals("'1987/05/02' + 1 SECOND", $this->_platform->getDateAddSecondsExpression("'1987/05/02'", 1));
        self::assertEquals("'1987/05/02' + 21 DAY", $this->_platform->getDateAddWeeksExpression("'1987/05/02'", 3));
        self::assertEquals("'1987/05/02' + 10 YEAR", $this->_platform->getDateAddYearsExpression("'1987/05/02'", 10));
        self::assertEquals("DAYS('1987/05/02') - DAYS('1987/04/01')", $this->_platform->getDateDiffExpression("'1987/05/02'", "'1987/04/01'"));
        self::assertEquals("'1987/05/02' - 4 DAY", $this->_platform->getDateSubDaysExpression("'1987/05/02'", 4));
        self::assertEquals("'1987/05/02' - 12 HOUR", $this->_platform->getDateSubHourExpression("'1987/05/02'", 12));
        self::assertEquals("'1987/05/02' - 2 MINUTE", $this->_platform->getDateSubMinutesExpression("'1987/05/02'", 2));
        self::assertEquals("'1987/05/02' - 102 MONTH", $this->_platform->getDateSubMonthExpression("'1987/05/02'", 102));
        self::assertEquals("'1987/05/02' - 15 MONTH", $this->_platform->getDateSubQuartersExpression("'1987/05/02'", 5));
        self::assertEquals("'1987/05/02' - 1 SECOND", $this->_platform->getDateSubSecondsExpression("'1987/05/02'", 1));
        self::assertEquals("'1987/05/02' - 21 DAY", $this->_platform->getDateSubWeeksExpression("'1987/05/02'", 3));
        self::assertEquals("'1987/05/02' - 10 YEAR", $this->_platform->getDateSubYearsExpression("'1987/05/02'", 10));
        self::assertEquals(' WITH RR USE AND KEEP UPDATE LOCKS', $this->_platform->getForUpdateSQL());
        self::assertEquals('LOCATE(substring_column, string_column)', $this->_platform->getLocateExpression('string_column', 'substring_column'));
        self::assertEquals('LOCATE(substring_column, string_column)', $this->_platform->getLocateExpression('string_column', 'substring_column'));
        self::assertEquals('LOCATE(substring_column, string_column, 1)', $this->_platform->getLocateExpression('string_column', 'substring_column', 1));
        self::assertEquals('SUBSTR(column, 5)', $this->_platform->getSubstringExpression('column', 5));
        self::assertEquals('SUBSTR(column, 5, 2)', $this->_platform->getSubstringExpression('column', 5, 2));
    }

    public function testModifiesLimitQuery()
    {
        self::assertEquals(
            'SELECT * FROM user',
            $this->_platform->modifyLimitQuery('SELECT * FROM user', null, null)
        );

        self::assertEquals(
            'SELECT db22.* FROM (SELECT db21.*, ROW_NUMBER() OVER() AS DC_ROWNUM FROM (SELECT * FROM user) db21) db22 WHERE db22.DC_ROWNUM <= 10',
            $this->_platform->modifyLimitQuery('SELECT * FROM user', 10, 0)
        );

        self::assertEquals(
            'SELECT db22.* FROM (SELECT db21.*, ROW_NUMBER() OVER() AS DC_ROWNUM FROM (SELECT * FROM user) db21) db22 WHERE db22.DC_ROWNUM <= 10',
            $this->_platform->modifyLimitQuery('SELECT * FROM user', 10)
        );

        self::assertEquals(
            'SELECT db22.* FROM (SELECT db21.*, ROW_NUMBER() OVER() AS DC_ROWNUM FROM (SELECT * FROM user) db21) db22 WHERE db22.DC_ROWNUM >= 6 AND db22.DC_ROWNUM <= 15',
            $this->_platform->modifyLimitQuery('SELECT * FROM user', 10, 5)
        );
        self::assertEquals(
            'SELECT db22.* FROM (SELECT db21.*, ROW_NUMBER() OVER() AS DC_ROWNUM FROM (SELECT * FROM user) db21) db22 WHERE db22.DC_ROWNUM >= 6 AND db22.DC_ROWNUM <= 5',
            $this->_platform->modifyLimitQuery('SELECT * FROM user', 0, 5)
        );
    }

    public function testPrefersIdentityColumns()
    {
        self::assertTrue($this->_platform->prefersIdentityColumns());
    }

    public function testSupportsIdentityColumns()
    {
        self::assertTrue($this->_platform->supportsIdentityColumns());
    }

    public function testDoesNotSupportSavePoints()
    {
        self::assertFalse($this->_platform->supportsSavepoints());
    }

    public function testDoesNotSupportReleasePoints()
    {
        self::assertFalse($this->_platform->supportsReleaseSavepoints());
    }

    public function testDoesNotSupportCreateDropDatabase()
    {
        self::assertFalse($this->_platform->supportsCreateDropDatabase());
    }

    public function testReturnsSQLResultCasing()
    {
        self::assertSame('COL', $this->_platform->getSQLResultCasing('cOl'));
    }

    protected function getBinaryDefaultLength()
    {
        return 1;
    }

    protected function getBinaryMaxLength()
    {
        return 32704;
    }

    public function testReturnsBinaryTypeDeclarationSQL()
    {
        self::assertSame('VARCHAR(1) FOR BIT DATA', $this->_platform->getBinaryTypeDeclarationSQL([]));
        self::assertSame('VARCHAR(255) FOR BIT DATA', $this->_platform->getBinaryTypeDeclarationSQL(['length' => 0]));
        self::assertSame('VARCHAR(32704) FOR BIT DATA', $this->_platform->getBinaryTypeDeclarationSQL(['length' => 32704]));

        self::assertSame('CHAR(1) FOR BIT DATA', $this->_platform->getBinaryTypeDeclarationSQL(['fixed' => true]));
        self::assertSame('CHAR(254) FOR BIT DATA', $this->_platform->getBinaryTypeDeclarationSQL(['fixed' => true, 'length' => 0]));
    }

    /**
     * @group legacy
     * @expectedDeprecation Binary field length 32705 is greater than supported by the platform (32704)
     */
    public function testReturnsBinaryTypeLongerThanMaxDeclarationSQL()
    {
        self::assertSame('BLOB(1M)', $this->_platform->getBinaryTypeDeclarationSQL(['length' => 32705]));
        self::assertSame('BLOB(1M)', $this->_platform->getBinaryTypeDeclarationSQL(['fixed' => true, 'length' => 32705]));
    }

    /**
     * @group DBAL-234
     */
    protected function getAlterTableRenameIndexSQL()
    {
        return array(
            'RENAME INDEX idx_foo TO idx_bar',
        );
    }

    /**
     * @group DBAL-234
     */
    protected function getQuotedAlterTableRenameIndexSQL()
    {
        return array(
            'RENAME INDEX "create" TO "select"',
            'RENAME INDEX "foo" TO "bar"',
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotedAlterTableRenameColumnSQL()
    {
        return array(
            'ALTER TABLE mytable ' .
            'RENAME COLUMN unquoted1 TO unquoted ' .
            'RENAME COLUMN unquoted2 TO "where" ' .
            'RENAME COLUMN unquoted3 TO "foo" ' .
            'RENAME COLUMN "create" TO reserved_keyword ' .
            'RENAME COLUMN "table" TO "from" ' .
            'RENAME COLUMN "select" TO "bar" ' .
            'RENAME COLUMN quoted1 TO quoted ' .
            'RENAME COLUMN quoted2 TO "and" ' .
            'RENAME COLUMN quoted3 TO "baz"'
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotedAlterTableChangeColumnLengthSQL()
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    /**
     * @group DBAL-807
     */
    protected function getAlterTableRenameIndexInSchemaSQL()
    {
        return array(
            'RENAME INDEX myschema.idx_foo TO idx_bar',
        );
    }

    /**
     * @group DBAL-807
     */
    protected function getQuotedAlterTableRenameIndexInSchemaSQL()
    {
        return array(
            'RENAME INDEX "schema"."create" TO "select"',
            'RENAME INDEX "schema"."foo" TO "bar"',
        );
    }

    /**
     * @group DBAL-423
     */
    public function testReturnsGuidTypeDeclarationSQL()
    {
        self::assertSame('CHAR(36)', $this->_platform->getGuidTypeDeclarationSQL(array()));
    }

    /**
     * {@inheritdoc}
     */
    public function getAlterTableRenameColumnSQL()
    {
        return array(
            'ALTER TABLE foo RENAME COLUMN bar TO baz',
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotesTableIdentifiersInAlterTableSQL()
    {
        return array(
            'ALTER TABLE "foo" DROP FOREIGN KEY fk1',
            'ALTER TABLE "foo" DROP FOREIGN KEY fk2',
            'ALTER TABLE "foo" ' .
            'ADD COLUMN bloo INTEGER NOT NULL WITH DEFAULT ' .
            'DROP COLUMN baz ' .
            'ALTER COLUMN bar DROP NOT NULL ' .
            'RENAME COLUMN id TO war',
            'CALL SYSPROC.ADMIN_CMD (\'REORG TABLE "foo"\')',
            'RENAME TABLE "foo" TO "table"',
            'ALTER TABLE "table" ADD CONSTRAINT fk_add FOREIGN KEY (fk3) REFERENCES fk_table (id)',
            'ALTER TABLE "table" ADD CONSTRAINT fk2 FOREIGN KEY (fk2) REFERENCES fk_table2 (id)',
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getCommentOnColumnSQL()
    {
        return array(
            'COMMENT ON COLUMN foo.bar IS \'comment\'',
            'COMMENT ON COLUMN "Foo"."BAR" IS \'comment\'',
            'COMMENT ON COLUMN "select"."from" IS \'comment\'',
        );
    }

    /**
     * @group DBAL-944
     *
     * @dataProvider getGeneratesAlterColumnSQL
     */
    public function testGeneratesAlterColumnSQL($changedProperty, Column $column, $expectedSQLClause = null)
    {
        $tableDiff = new TableDiff('foo');
        $tableDiff->fromTable = new Table('foo');
        $tableDiff->changedColumns['bar'] = new ColumnDiff('bar', $column, array($changedProperty));

        $expectedSQL = array();

        if (null !== $expectedSQLClause) {
            $expectedSQL[] = 'ALTER TABLE foo ALTER COLUMN bar ' . $expectedSQLClause;
        }

        $expectedSQL[] = "CALL SYSPROC.ADMIN_CMD ('REORG TABLE foo')";

        self::assertSame($expectedSQL, $this->_platform->getAlterTableSQL($tableDiff));
    }

    /**
     * @return array
     */
    public function getGeneratesAlterColumnSQL()
    {
        return array(
            array(
                'columnDefinition',
                new Column('bar', Type::getType('decimal'), array('columnDefinition' => 'MONEY NOT NULL')),
                'MONEY NOT NULL'
            ),
            array(
                'type',
                new Column('bar', Type::getType('integer')),
                'SET DATA TYPE INTEGER'
            ),
            array(
                'length',
                new Column('bar', Type::getType('string'), array('length' => 100)),
                'SET DATA TYPE VARCHAR(100)'
            ),
            array(
                'precision',
                new Column('bar', Type::getType('decimal'), array('precision' => 10, 'scale' => 2)),
                'SET DATA TYPE NUMERIC(10, 2)'
            ),
            array(
                'scale',
                new Column('bar', Type::getType('decimal'), array('precision' => 5, 'scale' => 4)),
                'SET DATA TYPE NUMERIC(5, 4)'
            ),
            array(
                'fixed',
                new Column('bar', Type::getType('string'), array('length' => 20, 'fixed' => true)),
                'SET DATA TYPE CHAR(20)'
            ),
            array(
                'notnull',
                new Column('bar', Type::getType('string'), array('notnull' => true)),
                'SET NOT NULL'
            ),
            array(
                'notnull',
                new Column('bar', Type::getType('string'), array('notnull' => false)),
                'DROP NOT NULL'
            ),
            array(
                'default',
                new Column('bar', Type::getType('string'), array('default' => 'foo')),
                "SET DEFAULT 'foo'"
            ),
            array(
                'default',
                new Column('bar', Type::getType('integer'), array('autoincrement' => true, 'default' => 666)),
                null
            ),
            array(
                'default',
                new Column('bar', Type::getType('string')),
                "DROP DEFAULT"
            ),
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotesReservedKeywordInUniqueConstraintDeclarationSQL()
    {
        return 'CONSTRAINT "select" UNIQUE (foo)';
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotesReservedKeywordInIndexDeclarationSQL()
    {
        return ''; // not supported by this platform
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotesReservedKeywordInTruncateTableSQL()
    {
        return 'TRUNCATE "select" IMMEDIATE';
    }

    /**
     * {@inheritdoc}
     */
    protected function supportsInlineIndexDeclaration()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function supportsCommentOnStatement()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function getAlterStringToFixedStringSQL()
    {
        return array(
            'ALTER TABLE mytable ALTER COLUMN name SET DATA TYPE CHAR(2)',
            'CALL SYSPROC.ADMIN_CMD (\'REORG TABLE mytable\')',
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getGeneratesAlterTableRenameIndexUsedByForeignKeySQL()
    {
        return array(
            'RENAME INDEX idx_foo TO idx_foo_renamed',
        );
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesTableNameInListTableColumnsSQL()
    {
        self::assertContains("'Foo''Bar\\'", $this->_platform->getListTableColumnsSQL("Foo'Bar\\"), '', true);
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesTableNameInListTableIndexesSQL()
    {
        self::assertContains("'Foo''Bar\\'", $this->_platform->getListTableIndexesSQL("Foo'Bar\\"), '', true);
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesTableNameInListTableForeignKeysSQL()
    {
        self::assertContains("'Foo''Bar\\'", $this->_platform->getListTableForeignKeysSQL("Foo'Bar\\"), '', true);
    }
}
