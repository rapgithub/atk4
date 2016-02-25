<?php
/**
 * Undocumented.
 */
class Field_Reference extends Field
{
    public $model_name = null;
    public $display_field = null;
    public $dereferenced_field = null;
    public $table_alias = null;

    /**
     * Set model
     *
     * @param Model|string $model
     * @param string $display_field
     *
     * @return Model
     */
    public function setModel($model, $display_field = null)
    {
        if (is_object($model)) {
            return AbstractObject::setModel($model);
        }

        $this->model_name = is_string($model) ? $model : get_class($model);
        $this->model_name = $this->app->normalizeClassName($this->model_name, 'Model');

        if ($display_field) {
            $this->display_field = $display_field;
        }

        if ($display_field !== false) {
            $this->owner->addExpression($this->getDereferenced())
                ->set(array($this, 'calculateSubQuery'))->caption($this->caption());
        }

        $this->system(true);
        $this->editable(true);
        $this->visible(false);

        return $this;
    }
    public function getModel()
    {
        if (!$this->model) {
            $this->model = $this->add($this->model_name);
        }
        if ($this->display_field) {
            $this->model->title_field = $this->display_field;
        }
        if ($this->table_alias) {
            $this->model->table_alias = $this->table_alias;
        }

        return $this->model;
    }
    public function sortable($x = undefined)
    {
        $f = $this->owner->hasElement($this->getDereferenced());
        if ($f) {
            $f->sortable($x);
        }

        return parent::sortable($x);
    }
    public function caption($x = undefined)
    {
        $f = $this->owner->hasElement($this->getDereferenced());
        if ($f) {
            $f->caption($x);
        }

        return parent::caption($x);
    }
    /**
     * ref() will traverse reference and will attempt to load related model's entry. If the entry will fail to load
     * it will return model which would not be loaded. This can be changed by specifying an argument:.
     *
     * 'model' - simply create new model and return it without loading anything
     * false or 'ignore' - will not even try to load anything
     * null (default) - will tryLoad()
     * 'load' - will always load the model and if record is not present, will fail
     * 'create' - if record fails to load, will create new record, save, get ID and insert into $this
     * 'link' - if record fails to load, will return new record, with appropriate afterSave hander, which will
     *          update current model also and save it too.
     */
    public function ref($mode = null)
    {
        if ($mode == 'model') {
            return $this->add($this->model_name);
        }

        $this->getModel()->unload();

        if ($mode === false || $mode == 'ignore') {
            return $this->model;
        }
        if ($mode == 'load') {
            return $this->model->load($this->get());
        }
        if ($mode === null) {
            if ($this->get()) {
                $this->model->tryLoad($this->get());
            }

            return $this->model;
        }
        if ($mode == 'create') {
            if ($this->get()) {
                $this->model->tryLoad($this->get());
            }
            if (!$this->model->loaded()) {
                $this->model->save();
                $this->set($this->model->id);
                $this->owner->update();

                return $this->model;
            }
        }
        if ($mode == 'link') {
            $m = $this->add($this->model_name);
            if ($this->get()) {
                $m->tryLoad($this->get());
            }
            $t = $this;
            if (!$m->loaded()) {
                $m->addHook('afterSave', function ($m) use ($t) {
                    $t->set($m->id);
                    $t->owner->saveLater();
                });
            }

            return $m;
        }
    }

    /**
     * @return SQL_Model
     */
    public function refSQL()
    {
        /** @var SQL_Model $q */
        $q = $this->ref('model');
        $q->addCondition($q->id_field, $this);

        return $q;
    }
    public function getDereferenced()
    {
        if ($this->dereferenced_field) {
            return $this->dereferenced_field;
        }
        $f = preg_replace('/_id$/', '', $this->short_name);
        if ($f != $this->short_name) {
            return $f;
        }

        $f = $this->short_name.'_text';
        if ($this->owner->hasElement($f)) {
            return $f;
        }

        $f = $this->_unique($this->owner->elements, $f);
        $this->dereferenced_field = $f;

        return $f;
    }
    public function destroy()
    {
        if ($e = $this->owner->hasElement($this->getDereferenced())) {
            $e->destroy();
        }

        return parent::destroy();
    }
    public function calculateSubQuery($model, $select)
    {
        if (!$this->model) {
            $this->getModel();
        } //$this->model=$this->add($this->model_name);

        if ($this->display_field) {
            $title = $this->model->dsql()->del('fields');
            $this->model->getElement($this->display_field)->updateSelectQuery($title);
        } elseif ($this->model->hasMethod('titleQuery')) {
            $title = $this->model->titleQuery();
        } else {
            // possibly references non-sql model, so just display field value
            return $this->owner->dsql()->bt($this->short_name);
        }
        $title->del('order')
            ->where($this, $title->getField($this->model->id_field));

        return $title;
    }
}
