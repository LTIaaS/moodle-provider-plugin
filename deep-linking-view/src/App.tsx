import React, { ReactNode } from 'react';
import './App.css';
import { Button } from "primereact/button";
import jdata from './data.json';
import { DataView } from 'primereact/dataview';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faChalkboard, faGraduationCap, faSpinner } from '@fortawesome/free-solid-svg-icons'
import { Tag } from 'primereact/tag';
import { classNames } from 'primereact/utils';
import axios from 'axios';
import { Dialog } from 'primereact/dialog';
import { ScrollTop } from 'primereact/scrolltop';

interface Course {
  url : string,
  name : string,
  description : string,
  parent : number,
  icon: string,
  type: string, //COURSE | MODULE
  depth : number,
  id : number,
  children : Course[]
}

function App() {

  const [data, setData] = React.useState<Course[]>([]);
  const [loading, setLoading] = React.useState(false);

  React.useEffect(() => {
    console.log(jdata)
    setData(jdata)
  }, [])

  const launch = (url: string) => {
    setLoading(true);
    console.log(url);
    axios.get('https://jsonplaceholder.typicode.com/posts')
      .then(response => {
        // body.append
        setLoading(false);
      })
      .catch(error => {
        console.error(error);
        setLoading(false);
      });
  }

  const getSeverity = (item: Course) => {
    switch (item.type) {
        case 'COURSE':
            return 'success';

        case 'MODULE':
            return 'warning';

        default:
            return null;
    }
};

  const getImage = (item: Course) => {
    if(item.icon.length > 0) {
      return <img onClick={() => launch(item.url)} style={{cursor: "pointer"}} className="w-9 sm:w-10rem xl:w-10rem shadow-2 block xl:block mx-auto border-round" src={item.icon} alt={item.name} />
    } else if(item.type === "COURSE") {
      return <FontAwesomeIcon onClick={() => launch(item.url)} style={{cursor: "pointer"}} className="w-9 sm:w-10rem xl:w-10rem shadow-2 block xl:block mx-auto border-round" icon={faGraduationCap} size="8x" />
    } else {
      return <FontAwesomeIcon onClick={() => launch(item.url)} style={{cursor: "pointer"}} className="w-9 sm:w-10rem xl:w-10rem shadow-2 block xl:block mx-auto border-round" icon={faChalkboard} size="8x" />
    }
  }

  const itemTemplate = (item: Course, index: any): ReactNode => {
    return (
      <div className="col-12" key={item.id}>
        <div className={classNames('flex flex-column sm:flex-row sm:align-items-start p-4 gap-4', { 'border-top-1 surface-border': index !== 0 })}>
          {getImage(item)}
          <div className="flex flex-column sm:flex-row justify-content-between align-items-center sm:align-items-start flex-1 gap-4">
            <div className="flex flex-column align-items-center sm:align-items-start gap-1">
              <div className="text-2xl font-bold text-900">{item.name}</div>
              <p style={{textAlign:"left"}}>
                {item.description}
              </p>
              <div className="flex align-items-center gap-1">
                <Tag value={item.type} severity={getSeverity(item)}></Tag>
              </div>
          </div>
            <div className="flex sm:flex-column align-items-center sm:align-items-end gap-3 sm:gap-2">
              <Button icon="pi pi-shopping-cart" className="p-button-rounded" disabled={item.url.length === 0} onClick={() => launch(item.url)}>Select</Button>
            </div>
          </div>
        </div>
      </div>
    );
  };

  return (
    <div className="App p-4">
      <div className="card shadow-2 block xl:block mx-auto border-round">
        <DataView value={data} itemTemplate={itemTemplate} layout={"list"} />
      </div>
      <Dialog header="Loading" visible={loading} onHide={() => {}} content={({ hide }) => (
        <div className='p-3' style={{borderRadius: '12px', backgroundColor: "white"}}>
          <p style={{textAlign: "center"}}>
            <b>
              Loading
            </b>
          </p>
          <FontAwesomeIcon className="fa-spin" icon={faSpinner} size="6x"/>
        </div>
      )} />
      <ScrollTop />
    </div>
  );
}

export default App;
