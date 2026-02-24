<?php

namespace App\Repositories\MySQL;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class BaseRepository
{

    /**
     * Objeto da classe realcionada ao respositorio
     *
     * @var $model
     */
    public $model;

    /**
     * BaseRepository constructor.
     */
    public function __construct()
    {

        $this->model = getNewModel( class_basename( $this ), "/System" );
    }

    /**
     * Cria a model no banco de dados
     *
     * @param array $data
     * @param bool $returnModel
     * @return bool
     */
    public function store( $data, $returnModel = false )
    {

        try {

            $model = $this->model->create(
                collect( $data )->only( $this->model->getFillable() )->toArray()
            );

        } catch ( QueryException $exception ) {

            return $this->logErrorAndReturn( $exception->getMessage() );

        }

        return $returnModel ? $model : true;
    }

    /**
     * Atualiza a model no banco de dados
     *
     * @param integer $id
     * @param array $data
     * @param bool $returnModel
     * @return bool
     */
    public function update( $id, $data, $returnModel = false ) {

        try {

            $model = $this->model->findOrFail( $id );

            $model->update( $data );

        } catch ( QueryException $exception ) {

            return $this->logErrorAndReturn( $exception->getMessage() );

        }

        return $returnModel ? $model : true;
    }

    /**
     * Remove a model do banco de dados
     *
     * @param $id
     * @return bool
     */
    public function delete( $id )
    {

        try {

            $model = $this->model->findOrFail( $id );

            $model->delete();

        } catch ( QueryException $exception ) {

            return $this->logErrorAndReturn( $exception->getMessage() );

        }

        return true;
    }

    /**
     * Retorna todos os itens da model
     *
     * @param array $relationships
     * @return mixed
     */
    public function all( $relationships = [] )
    {

        return $this->model->with( $relationships )->get();
    }

    /**
     * Retorna todos os itens ativos da model
     *
     * @param array $relationships
     * @return mixed
     */
    public function allActive( $relationships = [] )
    {

        return $this->model->with( $relationships )->where( 'active', '1' )->get();
    }

    /**
     * Faz a busca por parametros
     *
     * @param $parameters
     * @return mixed
     */
    public function find( $parameters )
    {

        return $this->model->where( $parameters )->first();
    }

    /**
     * Faz a busca pela chave primaria
     *
     * @param int|string $primaryKey
     * @return mixed
     */
    public function findByPk( $primaryKey )
    {

        return $this->model->find( $primaryKey );
    }

    /**
     * Retorna todos os registros da busca por parametros
     *
     * @param $parameters
     * @return mixed
     */
    public function get( $parameters )
    {

        return $this->model->where( $parameters )->get();
    }

    /**
     * Retorna uma instancia do query builder
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {

        return $this->model->query();
    }

    /**
     * Retorna registros com paginação
     *
     * @param int $limit
     * @param bool $simple
     * @return mixed
     */
    public function paginate( $limit = 15, $simple = false )
    {

        $method = $simple ? "simplePaginate" : "paginate";

        return $this->model->$method( $limit );
    }

    /**
     * Registra o log de erro da operação e retorna false
     *
     * @param $message
     * @return bool
     */
    public function logErrorAndReturn( $message )
    {
        Log::error( $message );

        return false;
    }

}
