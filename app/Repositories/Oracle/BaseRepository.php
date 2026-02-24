<?php

namespace App\Repositories\Oracle;

use Illuminate\Database\Eloquent\Builder;
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

        $this->model = getNewModel( class_basename( $this ), "/EMR" );
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
     * Retorna um numero limitado de registros
     *
     * @param $quantity
     * @param string $referenceColumn
     * @param array $relationships
     * @return mixed
     */
    public function limit( $quantity, $referenceColumn = 'id', $relationships = [] )
    {

        $direction = $quantity < 0 ? 'DESC' : 'ASC';

        return $this->model->with( $relationships )
            ->whereRaw( 'rownum <= ?', [ abs( $quantity ) ] )
            ->orderBy( $referenceColumn, $direction )
            ->get();

    }

    /**
     * Retorna todos os itens ativos da model
     *
     * @param array $relationships
     * @return mixed
     */
    public function allActive( $relationships = [] )
    {

        return $this->model->with( $relationships )->where('ie_situacao', 'A')->get();
    }

    /**
     * Faz a busca por parametros
     *
     * @param $parameters
     * @param array $relationships
     * @return mixed
     */
    public function find( $parameters, $relationships = [] )
    {

        return $this->model->where( $parameters )->with( $relationships )->first();
    }

    /**
     * Retorna a model pela sua chave primária
     *
     * @param $primaryKey
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
     * @return Builder
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
