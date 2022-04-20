
var uppy = new Uppy.Core({debug: debug})
.use(Uppy.Dashboard, {
    inline: true,
    target: '#drag-drop-area',
    allowMultipleUploads: false,
    id: 'uppyFile',
    autoProceed: true,
})
.use(Uppy.Form, {
    target: '#'+formId,                        
    resultName: 'uppyResult',
    getMetaFromForm: true,
    addResultToForm: true,
    multipleResults: false,
    submitOnSuccess: false,
    triggerUploadOnSubmit: false,
})       
.use(Uppy.AwsS3, {
    getUploadParameters (file) {
        let body = JSON.stringify({
                filename: file.name,
                contentType: file.type,
            });
        return fetch(signUrl, {
            method: 'post',
            headers: {
                accept: 'application/json',
                'content-type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: body,
        })                
        .then((response) => {
            return response.json()
        }).then((data) => {
            if (data.error){                    
                document.querySelector('.uploaded-response').innerHTML = file_not_saved;                                         
            }else{
                if (data.code!=200&&data.message!== undefined){                     
                    document.querySelector('.uploaded-response').innerHTML = data.message;
                    return false;
                }
                return {
                    method: data.method,
                    url: data.url,
                    fields: data.fields,
                    headers: data.headers,
                }
            }
        })
    }
});            

uppy.on('complete', (result) => {
    
    if (result.successful.length == 0) return;

    let objs = [];
    for(j in result.successful){
        let obj = {};
        obj.filename = result.successful[j].data.name;
        obj.filesize = result.successful[j].data.size;
        obj.mime_type = result.successful[j].data.type;
        obj.extension = result.successful[j].extension;
        obj.foreign_key = document.querySelector('input[name="foreign_key"]').value;           
        obj.model = document.querySelector('input[name="model"]').value;        
        let v = result.successful[j].uploadURL.split('/')
        obj.path = v[v.length-1];
        objs[objs.length] = obj;
    }

    let body = JSON.stringify({items: objs});
    fetch(saveUrl, {
        method: 'post',
        headers: {
            accept: 'application/json',
            'content-type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: body,
    })
    .then((resp) => resp.json())
    .then(function(data) {                                
        document.querySelector('.uploaded-response').innerHTML = data.result.message;                          
    })
    .catch(function(error) {
        document.querySelector('.uploaded-response').innerHTML = error.message;  
    });          

})