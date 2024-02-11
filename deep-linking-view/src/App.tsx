import React, { ReactNode } from 'react';
import './App.css';
import { Button } from "primereact/button";
import { DataView } from 'primereact/dataview';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faChalkboard, faGraduationCap, faCircleNotch } from '@fortawesome/free-solid-svg-icons'
import { Tag } from 'primereact/tag';
import { classNames } from 'primereact/utils';
import axios from 'axios';
import { Dialog } from 'primereact/dialog';
import { ScrollTop } from 'primereact/scrolltop';
import { Skeleton } from 'primereact/skeleton';
import { Toast } from 'primereact/toast';
import { error } from 'console';

interface Course {
  url : string,
  name : string,
  description : string,
  //parent : number,
  icon: string,
  type: string, //COURSE | MODULE
  depth : number,
  id : number,
  isSkeleton: boolean | null
}

const skeletonData: Course[] = [
  //@ts-ignore
  {isSkeleton: true}, {isSkeleton: true}, {isSkeleton: true}
]

function App() {

  const [data, setData] = React.useState<Course[]>(skeletonData);
  const [loading, setLoading] = React.useState(false);
  const toast = React.useRef<Toast>(null);

  React.useEffect(() => {
    // get the LTIK
    const search = window.location.search;
    const params = new URLSearchParams(search);
    const ltik = params.get('ltik');

    // get the deep-linking content items
    axios.get(`../deeplinking.php?ltik=${ltik}`)
      .then(response => {
        if(response.status === 200) {
          setData(response.data as Course[]);
        } else {
          toast.current?.show({ life: 5000, severity: 'error', summary: 'Error', detail: 'Unable to get data from server. Please refresh page and try again.' });
        }
      })
      .catch(error => {
        toast.current?.show({ life: 5000, severity: 'error', summary: 'Error', detail: 'Unable to get data from server. Please refresh page and try again.' });
        console.error(error);
      });
  }, [])

  const launch = (item: Course) => {
    setLoading(true);
    // get the LTIK
    const search = window.location.search;
    const params = new URLSearchParams(search);
    const ltik = params.get('ltik');

    let contentItem: any = {
      type: "ltiResourceLink",
      url: item.url,
      title: item.name
    };
    // add icon if it exists
    if(item.icon.length > 0) {
      contentItem = {...contentItem,
        icon: { 
          url: "https://lti.example.com/image.jpg", 
          width: 100, 
          height: 100 
        }, 
        thumbnail: { 
          url: "https://lti.example.com/thumb.jpg", 
          width: 90, 
          height: 90 
        }
      }
    }

    axios.post(`../deeplinkingform.php?ltik=${ltik}`,contentItem)
      .then(response => {
        if(response.status === 200) {
          if(response.data.err) {
            toast.current?.show({ life: 5000, severity: 'error', summary: 'Error', detail: response.data.err });
          } else {
            document.body.append(response.data.form);
          }
        } else {
          toast.current?.show({ life: 5000, severity: 'error', summary: 'Error', detail: 'Unable to get data from server. Please try again.' });
        }
        setLoading(false);
      })
      .catch(error => {
        console.error(error);
        toast.current?.show({ life: 5000, severity: 'error', summary: 'Error', detail: 'Unable to get data from server. Please try again.' });
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
      return <img onClick={() => launch(item)} style={{cursor: "pointer"}} className="w-9 sm:w-10rem xl:w-10rem shadow-2 block xl:block mx-auto border-round" src={item.icon} alt={item.name} />
    } else if(item.type === "COURSE") {
      return <FontAwesomeIcon onClick={() => launch(item)} style={{cursor: "pointer"}} className="w-9 sm:w-10rem xl:w-10rem shadow-2 block xl:block mx-auto border-round" icon={faGraduationCap} size="8x" />
    } else {
      return <FontAwesomeIcon onClick={() => launch(item)} style={{cursor: "pointer"}} className="w-9 sm:w-10rem xl:w-10rem shadow-2 block xl:block mx-auto border-round" icon={faChalkboard} size="8x" />
    }
  }

  const itemTemplate = (item: Course): ReactNode => {
    return (
      <div className="col-12" key={item.id}>
        <div className={classNames('flex flex-column sm:flex-row sm:align-items-start p-4 gap-4', 'border-top-1 surface-border')}>
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
              <Button icon="pi pi-shopping-cart" className="p-button-rounded" disabled={item.url.length === 0} onClick={() => launch(item)}>Select</Button>
            </div>
          </div>
        </div>
      </div>
    );
  };

  const listItemSkeleton = (item: Course) => {
    return (
      <div className="col-12" key={item.id}>
        <div className={classNames('flex flex-column xl:flex-row xl:align-items-start p-4 gap-4', 'border-top-1 surface-border')}>
          <Skeleton className="w-9 sm:w-16rem xl:w-10rem shadow-2 h-6rem block xl:block mx-auto border-round" />
          <div className="flex flex-column sm:flex-row justify-content-between align-items-center xl:align-items-start flex-1 gap-4">
            <div className="flex flex-column align-items-center sm:align-items-start gap-3">
              <Skeleton className="w-8rem border-round h-2rem" />
              <Skeleton className="w-6rem border-round h-1rem" />
              <div className="flex align-items-center gap-3">
                <Skeleton className="w-3rem border-round h-1rem" />
              </div>
            </div>
            <div className="flex sm:flex-column align-items-center sm:align-items-end gap-3 sm:gap-2">
              <Skeleton className="w-4rem border-round h-2rem" />
              <Skeleton shape="circle" className="w-3rem h-3rem" />
            </div>
          </div>
        </div>
      </div>
    );
  };

  const getItem = (item: Course, layout: any) => {
    if(item?.isSkeleton) {
      return listItemSkeleton(item);
    } else {
      return itemTemplate(item);
    }
  };

  return (
    <div className="App p-4">
      <div className="card shadow-2 block xl:block mx-auto border-round">
        <DataView value={data} itemTemplate={getItem} layout={"list"} />
      </div>
      <Dialog header="Loading" visible={loading} onHide={() => {}} content={({ hide }) => (
        <div className='p-3' style={{borderRadius: '12px', backgroundColor: "white"}}>
          <p style={{textAlign: "center"}}>
            <b>
              Loading
            </b>
          </p>
          <FontAwesomeIcon className="fa-spin" icon={faCircleNotch} size="6x"/>
        </div>
      )} />
      <ScrollTop />
      <Toast ref={toast} />
    </div>
  );
}

export default App;
