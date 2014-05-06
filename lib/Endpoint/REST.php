<?php
/**
 * Implementation of RESTful endpoint for App_REST
 */

// @codingStandardsIgnoreStart because REST is acronym
class Endpoint_REST extends AbstractModel
{
// @codingStandardsIgnoreEnd

    public $doc_url='app/rest';

    public $model_class=null;
    public $user_id_field='user_id';
    public $user=null;  // authenticated user

    public $authenticate=null;

    public $allow_list=true;
    public $allow_list_one=true;
    public $allow_add=false;
    public $allow_edit=false;
    public $allow_delete=false;

    public $app;
    public $api;


    /**
     * init
     *
     * @return void
     */
    function init()
    {
        parent::init();
        // first let's see if we authenticate
        if ($this->authenticate === true || ($this->authenticate !== false && ($this->hasMethod('authenticate') || $this->app->hasMethod('authenticate')))) {
            $result=false;
            if ($this->hasMethod('authenticate')) {
                $result = $this->authenticate();
            }

            if (!$result && $this->app->hasMethod('authenticate')) {
                $result = $this->app->authenticate();
            }

            if (!$result) {
                throw $this->exception('Authentication Failed', null, 403);
            }

            if (is_object($result)) {
                $this->user=$result;
            }
        }

        $m=$this->_model();

        if($m)$this->setModel($m);
    }


    /**
     * Method returns new instance of the model we will operate on. Instead of
     * using this method, you can use $this->model instead
     *
     * @return Model [description]
     */
    protected function _model()
    {
        // Based od authentication data, return a valid model
        if(!$this->model_class)return false;

        $m=$this->app->add('Model_'.$this->model_class);
        if ($this->user_id_field && $m->hasField($this->user_id_field) && $this->authenticate !== false) {
            // if not authenticated, blow up
            $m->addCondition($this->user_id_field, $this->user->id);
        }

        $id=$_GET['id'];
        if (!is_null($id)) {
            $m->load($id);
        }

        return $m;

    }

    /**
     * Generic method for returning single record item of data, which can be
     * used for filtering or cleaning up.
     *
     * @param [type] $data [description]
     *
     * @return [type] [description]
     */
    protected function outputOne($data)
    {
        if(is_object($data))
            $data=$data->get();
        if ($data['_id']) {
            $data = array('id'=>(string) $data['_id'])+$data;
        }
        unset($data['_id']);

        foreach ($data as $key => $val) {
            if ($val instanceof MongoID) {
                $data[$key]=(string) $val;
            }
        }

        return $data;
    }

    /**
     * Generic outptu filtering method for multiple records of data
     *
     * @param [type] $data [description]
     *
     * @return [type] [description]
     */
    protected function outputMany($data)
    {

        if(is_object($data))
            $data=$data->getRows();

        $output = array();
        foreach ($data as $row) {
            $output[] = $this->outputOne($row);
        }

        return $output;
    }

    /**
     * Filtering input data
     *
     * @param type    $data   [description]
     * @param boolean $filter [description]
     *
     * @return [type]          [description]
     */
    protected function _input($data, $filter = true)
    {
        // validates input
        if (is_array($filter)) {
            $data = array_intersect_key($my_array, array_flip($allowed));
        }

        unset($data['id']);
        unset($data['_id']);
        unset($data['user_id']);
        unset($data['user']);

        return $data;
    }

    /**
     * [get description]
     *
     * @return [type] [description]
     */
    public function get()
    {
        $m=$this->model;
        if(!$m)throw $this->exception('Specify model_class or define your method handlers');

        if ($m->loaded()) {
            if(!$this->allow_list_one)throw $this->exception('Loading is not allowed');

            return $this->outputOne($m->get());
        }

        if(!$this->allow_list)throw $this->app->exception('Listing is not allowed');

        return $this->outputMany($m->setLimit(100)->getRows());
    }

    /**
     * see get()
     *
     * @return [type] [description]
     */
    public function head()
    {
        return $this->get();
    }

    /**
     * [insert description]
     *
     * @param [type] $data [description]
     *
     * @return [type]       [description]
     */
    public function post($data)
    {
        $m=$this->model;

        if(!$this->allow_add)throw $this->exception('Adding is not allowed');

        if($m->loaded()) throw $this->exception('Not a valid request for this resource URL');

        $data=$this->_input($data, $this->allow_add);

        return $this->outputOne($m->set($data)->save()->get());
    }

    /**
     * [save description]
     *
     * @param [type] $data [description]
     *
     * @return [type]       [description]
     */
    public function put($data)
    {
        $m=$this->model;

        if(!$m->loaded())throw $this->exception('Replacing of the whole collection is not supported. element URI');

        if(!$this->allow_edit)throw $this->exception('Editing is not allowed');

        $data=$this->_input($data, $this->allow_edit);


        return $this->outputOne($m->set($data)->save()->get());
    }

    /**
     * See put()
     *
     * @param [type] $data [description]
     *
     * @return [type]       [description]
     */
    public function patch($data)
    {
        return $this->put($data);
    }

    /**
     * [delete description]
     *
     * @return [type] [description]
     */
    public function delete()
    {
        if(!$this->allow_delete)throw $this->exception('Deleting is not allowed');

        $m=$this->model;
        if(!$m->loaded())throw $this->exception('Cowardly refusing to delete all records');

        $m->delete();

        return true;
    }
}
