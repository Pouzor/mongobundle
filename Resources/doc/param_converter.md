# Using ParamConverter features

MongoBundle bring us also the possibility of converting parameters from urls into mongo types. The supported types are MongoId and mongo documents

## MongoId

Lets see an example:

    /**
     * @param $id
     * @Get
     * @ParamConverter("id", class="MongoId", converter="mongoId")
     * @return Response
     */
    public function getTaskAction(\MongoId $id)
    {
        return $this->createResponse($this->getApi()->get($id));
    }


As you can see, you must specify the converter name to call the mongoid converter. This converter can process urls like:

http://v2.api.dev/ord/tasks/53fefc976d819aca495eb1b1

where the last value correspond to a MongoId. The converter will make the conversion and the parameter delivered to controller will be ready to be used into database
tasks.


## Mongo Documents

This converter will search into the database the object corresponding to the parameters passed as arguments for conversion.


    /**
     * @param $oldTask
     * @Put("/tasks/{oldTask}")
     * @ParamConverter("oldTask", options={ "collection": "Task", "findBy": "_id" }, converter="mongo_document")
     * @return Response
     */
    public function putTasksAction($oldTask)
    {
        $task = $this->get('request')->request->get('task');

        try {
            return $this->createResponse($this->getApi()->update($oldTask, $task), Codes::HTTP_OK);
        } catch (\Exception $e) {
            return $this->createResponse($e, Codes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

You must specify always the converter name in order to indicate bypassing all considerations for other param converters.


Happy coding!
