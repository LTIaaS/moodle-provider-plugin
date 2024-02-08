import React, { ReactNode } from 'react';
import './App.css';
import { Button } from "primereact/button";
import { Card } from "primereact/card";
import { Toolbar } from 'primereact/toolbar';
import jdata from './data.json';
import { InputText } from 'primereact/inputtext';
import { DataView, DataViewLayoutOptions } from 'primereact/dataview';

interface Course {
  url : string,
  name : string,
  description : string,
  parent : number,
  depth : number,
  id : number,
  children : Course[]
}

function App() {

  const [data, setData] = React.useState<Course[]>([]);
  const [search, setSearch] = React.useState("");
  const [layout, setLayout] = React.useState<"grid" | "list" | (string & Record<string, unknown>) | undefined>('grid');

  React.useEffect(() => {
    console.log(jdata)
    setData(jdata)
  }, [])

  const launch = (url: string) => {
    console.log(url);
  }

  const header = () => {
    return (
      <div className="flex justify-content-end">
        <div className="flex">
          <span className="p-input-icon-left mr-2">
            <i className="pi pi-search" />
            <InputText placeholder="Search" />
          </span> 
          <DataViewLayoutOptions layout={layout} onChange={(e) => setLayout(e.value)}/>
        </div>
      </div>
    );
  };

  const itemTemplate = (item: any, layout: any): ReactNode => {
    return <Card
        title={
          <>
            {item.name}
            {" "}
            <Button label="Select" onClick={() => launch(item.url)}/>
          </>
        }
        className='mb-2'
      >
        <p className="m-0">
            {item.description}
            <i className="fa-solid fa-chalkboard"></i>
        </p>
      </Card>;
  }

  const getType = (item: Course) => {
    return "Course";
  }
/*
  const listItem = (product, index) => {
    return (
        <div className="col-12" key={product.id}>
            <div className={classNames('flex flex-column xl:flex-row xl:align-items-start p-4 gap-4', { 'border-top-1 surface-border': index !== 0 })}>
                <img className="w-9 sm:w-16rem xl:w-10rem shadow-2 block xl:block mx-auto border-round" src={`https://primefaces.org/cdn/primereact/images/product/${product.image}`} alt={product.name} />
                <div className="flex flex-column sm:flex-row justify-content-between align-items-center xl:align-items-start flex-1 gap-4">
                    <div className="flex flex-column align-items-center sm:align-items-start gap-3">
                        <div className="text-2xl font-bold text-900">{product.name}</div>
                        <div className="flex align-items-center gap-3">
                            <span className="flex align-items-center gap-2">
                                <i className="pi pi-tag"></i>
                                <span className="font-semibold">{product.category}</span>
                            </span>
                            <Tag value={product.inventoryStatus} severity={getType(product)}></Tag>
                        </div>
                    </div>
                    <div className="flex sm:flex-column align-items-center sm:align-items-end gap-3 sm:gap-2">
                        <span className="text-2xl font-semibold">${product.price}</span>
                        <Button icon="pi pi-shopping-cart" className="p-button-rounded" disabled={product.inventoryStatus === 'OUTOFSTOCK'}></Button>
                    </div>
                </div>
            </div>
        </div>
    );
};

const gridItem = (product: Course) => {
    return (
        <div className="col-12 sm:col-6 lg:col-12 xl:col-4 p-2" key={product.id}>
            <div className="p-4 border-1 surface-border surface-card border-round">
                <div className="flex flex-wrap align-items-center justify-content-between gap-2">
                    <div className="flex align-items-center gap-2">
                        <i className="pi pi-tag"></i>
                        <span className="font-semibold">{product.category}</span>
                    </div>
                    <Tag value={product.inventoryStatus} severity={getType(product)}></Tag>
                </div>
                <div className="flex flex-column align-items-center gap-3 py-5">
                    <img className="w-9 shadow-2 border-round" src={`https://primefaces.org/cdn/primereact/images/product/${product.image}`} alt={product.name} />
                    <div className="text-2xl font-bold">{product.name}</div>
                    <Rating value={product.rating} readOnly cancel={false}></Rating>
                </div>
                <div className="flex align-items-center justify-content-between">
                    <span className="text-2xl font-semibold">${product.price}</span>
                    <Button icon="pi pi-shopping-cart" className="p-button-rounded" disabled={product.inventoryStatus === 'OUTOFSTOCK'}></Button>
                </div>
            </div>
        </div>
    );
};

const itemTemplate = (product, layout, index) => {
    if (!product) {
        return;
    }

    if (layout === 'list') return listItem(product, index);
    else if (layout === 'grid') return gridItem(product);
};

const listTemplate = (products, layout) => {
    return <div className="grid grid-nogutter">{products.map((product, index) => itemTemplate(product, layout, index))}</div>;
};
*/
  return (
    <div className="App">
      <div className="grid mt-3">
        <div className='col-4' />
        <div className='col-4'>
          <DataView value={data} itemTemplate={itemTemplate} layout={"list"} header={header()} />
        </div>
        <div className='col-4' />
      </div>
    </div>
  );
}

export default App;
